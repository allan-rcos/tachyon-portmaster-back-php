# Adding a feature

A worked example, end to end: **Shipment** — a dispatched container's onward
journey, with a carrier and a destination. Every step names the existing file to
copy, so you are never inventing a shape.

Read [`architecture.md`](../architecture.md) first if the layering is not yet
familiar.

Throughout: replace `Shipment`/`shipment` with your own noun.

---

## 1. Schema

Schemas live in the `swagger/` submodule and are shared with the Go test suite.

`swagger/flatbuffers/schemas/shipment.fbs`:

```fbs
namespace API.Fbs.Shipment;

table ShipmentCreateRequest {
  container_id: string;
  carrier:      string;
  destination:  string;
}

table ShipmentResponse {
  id:           string;
  container_id: string;
  carrier:      string;
  destination:  string;
}

table ShipmentListResponse {
  data:  [ShipmentResponse];
  total: int;
  next:  string;
}
```

Generate:

```bash
dagger call generate-fbs-php                  # PHP tables under src/API/Fbs/Shipment/
(cd dagger && dagger call generate-fbs-go \
   export --path ../tests/integration/internal/fbs)   # Go bindings for the tests
```

Then write, per table, a **DTO** and its **factory** under
`src/API/Negociation/DTO/Shipment/`. The generated classes are overwritten on
every run, so `src/API/Fbs/` holds nothing but them, and everything the
application decides about a message lives in the factory.

The `X` marks the internal type: it goes before a trailing `Request`/`Response`,
or at the end when the schema name has neither (`UserX`).

Mirror, for a response: `src/API/Negociation/DTO/Product/ProductXResponse.php`
and `ProductXResponseFactory.php`.

```php
final readonly class ShipmentXResponse
{
    public function __construct(
        public ?string $id = null,
        public ?string $containerId = null,
        // ...
    ) {}
}

final readonly class ShipmentXResponseFactory implements IResponseAbstractFactory
{
    /** @var IResponseAbstractFactory|null The nested container, wrapped once. */
    private ?IResponseAbstractFactory $containerFactory;

    public function __construct(private ShipmentXResponse $message)
    {
        // A nested message's factory is built here, not in the methods below:
        // it is wrapped once, however many times the message is rendered.
        $this->containerFactory = $message->container !== null
            ? new ContainerXResponseFactory($message->container)
            : null;
    }

    // The builder is the strategy's — write the table into it, answer the
    // offset, and never call finish(). Children go in first, through this very
    // interface, because FlatBuffers stores them as offsets into this buffer.
    public function createFlatbuffer(FlatbufferBuilder $builder): Result
    {
        $container = $this->containerFactory?->createFlatbuffer($builder)->getValue() ?? 0;
        $id = $this->message->id !== null ? $builder->createString($this->message->id) : 0;

        return Result::success(ShipmentResponse::createShipmentResponse($builder, $id, $container));
    }

    // Keyed by the *schema's* names, snake_case — not the DTO's properties.
    public function createJson(): Result { /* ... */ }
}
```

Those two methods are the factory's whole surface. A parent nests a child by
calling the same two on it, so nothing has to live outside the contract and no
factory ever binds to another factory's class.

A response is only ever written and a request only ever read, so a request's
factory implements `IRequestAbstractFactory` instead — `fromJson()` and
`fromFlatbuffer()`, one per format a body can arrive in. Reading the root table
and copying its fields is `fromFlatbuffer()`'s whole job, written out in it. Every one of those methods
answers a `Result`, like everything else that can fail. Neither factory branches
on the wire format: that is the strategies' job, and there are
exactly two of each for the whole application.

Update `swagger/swagger.json` with the new endpoints.

---

## 2. Migration

Mirror: `db/migrations/000001_initial_schema.up.sql`.

`db/migrations/000003_shipments.up.sql`:

```sql
CREATE TABLE IF NOT EXISTS shipments (
    id            BIGINT UNSIGNED NOT NULL,
    container_id  BIGINT UNSIGNED NOT NULL,
    carrier       VARCHAR(255)    NOT NULL,
    destination   VARCHAR(255)    NOT NULL,
    search_carrier VARCHAR(255)   NOT NULL,
    PRIMARY KEY (id),
    KEY idx_shipments_container (container_id),
    KEY idx_shipments_search_carrier (search_carrier),
    CONSTRAINT fk_shipments_container FOREIGN KEY (container_id)
        REFERENCES containers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`db/migrations/000003_shipments.down.sql`:

```sql
DROP TABLE IF EXISTS shipments;
```

**The `down` is not optional** — the test harness resets by dropping and
re-migrating, so a missing or wrong `down` breaks every integration test, not
just yours.

`IF NOT EXISTS` / `IF EXISTS` on every statement: see
[`../database.md`](../database.md).

Only touch `db/seeds/dev.sql` if the feature needs dev fixtures — and then keep
it idempotent (`ON DUPLICATE KEY UPDATE`), because the compose `seed` service
re-runs on every start and `app` will not start if it fails.

---

## 3. Domain

Validation lives here and nowhere else.

**Model contract** — mirror `src/Domain/Models/IProduct.php`:

```php
// src/Domain/Models/IShipment.php
interface IShipment
{
    public string $id { get; }
    public string $containerId { get; }
    public string $carrier { get; }
    public string $destination { get; }
}
```

**Model** — mirror `src/Domain/Models/Internal/Product.php`. A readonly class
with a promoted constructor; no behaviour.

**Table module contract and implementation** — mirror
`src/Domain/TableModules/IProductTM.php` and
`src/Domain/TableModules/Interno/ProductTM.php`:

```php
final readonly class ShipmentTM implements IShipmentTM
{
    public function __construct(private IDatabaseIdGenerator $ids) {}

    public function create(string $containerId, string $carrier, string $destination): Result
    {
        if (trim($carrier) === '') {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'A shipment needs a carrier.',
                code: 422,
            )));
        }

        return Result::success(new Shipment(
            id: $this->ids->generate(),
            containerId: $containerId,
            carrier: $carrier,
            destination: $destination,
        ));
    }
}
```

Rules: return `Result`, never throw; 422 for a broken rule, 409 for a conflicting
state. Register the TM in `src/Domain/Interno/DomainProvider.php` and add its
accessor to `IDomainProvider`.

---

## 4. Infra

**Write side** — mirror `src/Infra/Repository/IProductRepository.php` and
`Interno/SqlProductRepository.php`. The repository takes `IPdoTransaction` and
enlists in the caller's boundary; it persists, it does not validate.

Use `Infra\Text\SearchKey` to fill `search_carrier`.

**Read side** — mirror `src/Infra/Query/Interno/ListProductsDQL.php` plus
`src/Infra/Query/Product/ProductListView.php` and `ProductViewItem.php`.

A `DQL` selects exactly the columns the endpoint returns and maps rows to a
`View`. It does **not** reconstitute a domain model. Cursor pagination goes
through `Infra\Query\Cursor`.

Register both in `src/Infra/Interno/InfraProvider.php` and declare them on
`IInfraProvider`.

---

## 5. Application

**Command** — mirror `src/App/Commands/Product/CreateProductCommand.php`. A
readonly DTO. The first property is always `UserContext $context`.

**Query** — mirror `src/App/Queries/Product/ListProductsQuery.php`.

**Use case contract** — mirror `src/App/Services/ICreateProductUseCase.php`.

**Use case** — mirror `src/App/Services/Interno/CreateProductUseCase.php`:

```php
final readonly class CreateShipmentUseCase implements ICreateShipmentUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IShipmentRepository $shipments,
        private IShipmentTM $shipmentTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'shipment:create');
    }

    public function execute(CreateShipmentCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $built = $this->shipmentTM->create(
            $command->containerId, $command->carrier, $command->destination,
        );
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IShipment $shipment */
        $shipment = $built->getValue();

        $inserted = $this->shipments->insert($shipment);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($shipment);
    }
}
```

The permission is declared **in the constructor**, which runs at `WorkerStart`.
That is what puts `shipment:create` in the catalogue, and therefore what makes
`POST /setup` grant it — no list to maintain anywhere.

**Feature provider** — mirror `src/App/Interno/Provider/ProductProvider.php`.
Extend `FeatureProvider` and use `$this->registrar()`:

```php
final class ShipmentProvider extends FeatureProvider
{
    private ?ICreateShipmentUseCase $createShipment = null;

    public function createShipmentUseCase(): ICreateShipmentUseCase
    {
        return $this->createShipment ??= new CreateShipmentUseCase(
            $this->infra->unitOfWork(),
            $this->infra->shipmentRepository(),
            $this->domain->shipmentTM(),
            $this->registrar(),
        );
    }
}
```

Then re-export from `src/App/Interno/AppProvider.php` and declare on
`IAppProvider`.

---

## 6. API

**Controller contract and implementation** — mirror
`src/API/Controllers/IProductController.php` and
`Interno/ProductController.php`.

The controller resolves the caller, builds the command, and maps the result. It
does **not** check permissions:

```php
public function create(ServerRequestInterface $request): ResponseInterface
{
    $caller = $this->caller();
    if (!$caller->isSuccess()) {
        return ProblemResponse::fromResult($this->accepts, $caller);
    }

    // The strategy was chosen by the negotiation middleware; the controller
    // only tells it which message to build — and what its answer means.
    $decoded = $this->contentType->execute($request->getBody(), new ShipmentCreateXRequestFactory());
    if (!$decoded->isSuccess()) {
        return ProblemResponse::fromResult($this->accepts, $decoded);
    }

    // A success always carries the message. No body at all, or one that does
    // not parse, is the failure above — the controller never builds a request
    // object to stand in for a message that did not arrive.
    $body = $decoded->getValue();

    $result = $this->createShipment->execute(new CreateShipmentCommand(
        context: $caller->getValue(),
        containerId: (string) $body->containerId,
        carrier: (string) $body->carrier,
        destination: (string) $body->destination,
    ));
    if (!$result->isSuccess()) {
        return ProblemResponse::fromResult($this->accepts, $result);
    }

    /** @var IShipment $shipment */
    $shipment = $result->getValue();

    $response = ApiResponse::body($this->accepts, new ShipmentXResponseFactory(new ShipmentXResponse(
        id: $shipment->id,
        // ...
    )), 201);

    // Rendering can fail too, and the controller decides what that means: a 502,
    // because the message was built and the server could not put it on the wire.
    return $response->isSuccess()
        ? $response->getValue()
        : ProblemResponse::fromResult($this->accepts, $response);
}
```

The controller takes `IContentTypeStrategy` and/or `IAcceptsStrategy` in its
constructor. Both are the same two context objects for the whole worker — see
[ADR 0009](../adr/0009-abstract-factory-e-strategy-na-negociacao.md) for why the
per-request choice lives in the coroutine context and not on them.

**Routes** — the table of the current contract version, today
`src/API/Http/Router/Interno/V1Router.php`. Ids are Base62, so use `self::ID`,
never `\d+`:

```php
new Route('GET',  '/shipments', [IShipmentController::class, 'list']),
new Route('POST', '/shipments', [IShipmentController::class, 'create']),
new Route('GET',  '/shipments/'.self::ID, [IShipmentController::class, 'get']),
```

A literal segment must come **before** the `{id}` pattern that would also match
it — `/containers/summary` before `/containers/{id}`. The set preserves insertion
order, so the position in the list is the position in the dispatcher.

Nothing else to wire: `RouterHub` mounts every `IVersionedRouter` under its own
`/v<n>` and serves the same routes unversioned, each resolving to the newest
version that publishes it. A new endpoint is one line in the table it belongs to.

**Wiring** — add the controller to the registry in
`src/API/Interno/ApiProvider.php`.

---

## 7. Tests

**Unit — the table module** (mirror `tests/Unit/Domain/ProductTMTest.php`): one
case per rule, plus the happy path.

**Unit — the use case** (mirror `tests/Unit/App/CreateProductUseCaseTest.php`):
commit on success, rollback on every failure, and the 403 guard. Remember
`Leaf::flushProcessErrors()` in `beforeEach`.

**Integration** — factories in
`tests/integration/internal/factories/shipment.go`, valid and invalid together,
then steps appended to `yard_story_test.go` (a shipment follows a dispatched
container, so it belongs to that narrative rather than a new one).

```bash
dagger call pest
dagger call integration-test -run TestYardStory
```

---

## 8. Documentation

PHPDoc on every new class in the project format — see
[`../documentation.md`](../documentation.md), or run the `phpdoc` skill to
align it.

Write an ADR in [`../adr/`](../adr/) if you made a decision a future reader
would otherwise have to reverse-engineer. Not for routine work.

---

## 9. Verify

```bash
dagger call phpstan            # level 9, must be clean
dagger call pest
dagger call integration-test
dagger call docs
```

---

## Checklist

- [ ] `.fbs` schema, and `swagger.json` updated
- [ ] Proxies written for every generated table
- [ ] Go bindings regenerated **and committed** (CI fails on stale ones)
- [ ] Migration has a symmetric `down.sql`
- [ ] Every DDL statement idempotent
- [ ] Validation is in the table module, nowhere else
- [ ] Every fallible path returns `Result`, never throws
- [ ] Permission declared in the use case constructor
- [ ] Every rollback path covered — no `commit` without its matching `rollback` test
- [ ] Literal routes registered before `{id}` patterns
- [ ] Registered in all four providers that need it
- [ ] PHPStan level 9 clean
