# 0009. Abstract factory and strategy instead of one proxy per message

**Status:** Accepted · 2026-08-03

## Context

[ADR 0001](0001-flatbuffers-over-json.md) put a hand-written `*Proxy` beside
every generated table, and gave it everything the application needed: a typed
constructor, `buildInto`, `toBinary`, JSON coercion. That was the right call for
what it solved — the generated PHP has nowhere to put anything.

Then negotiation arrived, and `fromStream()`/`toStream()` went onto the proxy
too. The proxy became six things at once: the DTO, the FlatBuffers writer, the
FlatBuffers reader, the JSON mapper, **the thing that decides the wire format**,
and the type the controller instantiates. With 39 messages, the decision that is
made once per request — binary or JSON — was written out 78 times, once per
direction per message, each a verbatim copy of the last.

The structural cost was worse than the duplication. Because a proxy extends its
generated table, `src/API/Fbs/` was half machine-written and half hand-written,
which is why `phpstan-generated-baseline.neon` needs 1600 lines to tell the two
apart. And because the proxy inherits the table's reader, three of them had to
override flatc's mis-namespaced nested getters to be readable at all — code that
existed only so a *response* could be parsed, which the server never does.

## Decision

Split the proxy along the seam that was already there — *what* the message is
versus *how* it is encoded — into three kinds of class, under
`src/API/Negociation/`:

- **A DTO** (`LoginXRequest`, `ProductListXResponse`), in `Negociation/DTO/`.
  Readonly, no inheritance, no behaviour. The `X` marks it as the internal type,
  which is also what keeps it from colliding with the generated table of the
  same schema name.
- **An abstract factory per message.** Inbound ones implement
  `IRequestAbstractFactory` and are stateless: `fromJson(array)` and
  `fromFlatbuffer(ByteBuffer)`. Outbound ones implement
  `IResponseAbstractFactory`, take the DTO in the constructor — along with the
  factories of anything nested in it, built once there and never again — and
  expose `createJson()` and `createFlatbuffer($builder)`. The schema's
  snake_case key names live here, not on the DTO: mapping a message onto a
  representation is building it. One factory method per representation, and no
  second door onto the same one: the JSON side returns the array itself rather
  than something to be serialized later.

  **The builder belongs to the strategy.** A factory writes its table into the
  builder it is handed and answers the offset; nobody finishes the buffer but
  the strategy that created it. That is what makes nesting work — FlatBuffers
  stores a child as an offset into the *same* buffer — and it is why those two
  methods are the factory's entire surface: a parent nests a child by calling
  the very same contract, so nothing outside it is needed and no factory ever
  binds to another factory's class.
- **A strategy per wire format**, two each way (`JsonContentTypeStrategy`,
  `FlatbufferContentTypeStrategy`, and their `Accepts` twins). Each knows a
  technique and no message whatsoever.

The pairing is double dispatch: `ContentNegotiationMiddleware` resolves the two
headers to a strategy, the controller hands the strategy a factory, and the
strategy calls the method that matches its format. The format branch exists
twice in the codebase — once per direction — instead of 78 times.

**The strategies are singletons, built by the provider like everything else.**
None of them holds state, so there is one of each per worker; the middleware
receives all four in its constructor, typed by the interface, and records a
*reference* to one of them for the request. It never builds one, and neither
does a context: the fallback each context uses when nothing was negotiated is
injected too.

**Everything that can fail answers a `Result`** — the factories and the
strategies both, and `ApiResponse::body()` with them. A body that arrived and
could not be read is a failure, and so is a request that carried no body at all:
only an action that reads a message calls a strategy, so "there is none" and "it
is unreadable" are the same answer to the same question. Neither reaches the
factory — there is nothing to build from — and neither is papered over with an
invented message, by the strategy or by the controller. A success therefore
always carries its value, which is why no caller checks it. What a failure
*becomes* is not decided anywhere in `Negociation/`:
the controller unwraps the `Result` and answers 404 for a request it could not
read, 502 for a response it could not render. `ProblemResponse::make()` is the
single exception, because it is what those answers are built out of — it falls
back to a hand-written JSON document rather than handing its caller a failure it
has nowhere to send.

**A response is write-only and a request is read-only.** Nothing generates a
`*Response` from the wire, so the outbound factories have no reader, and the
three overridden getters went away with it.

**Errors negotiate too.** `ProblemDetails` is a table of the published schema
like any other, so a caller asking for binary now gets the problem document in
binary. RFC 7807's `application/problem+json` survives as the media type of the
JSON branch, and follows from the status code: `ContentKind::mediaType($status)`.

**A strategy renders bytes and never names them.** `mediaType()` and
`problemMediaType()` were on `IAcceptsStrategy` at first, and they were a way of
asking a strategy *which strategy it is* — exactly what the pattern exists to
prevent. The `Content-Type` is now stamped by `ContentNegotiationMiddleware` on
the way out, from the `ContentKind` it resolved on the way in: the one place
that both read `Accept` and sees the finished response. `ApiResponse` and
`ProblemResponse` set no header at all, and a response that named itself — only
`ProblemResponse`'s hand-written last resort — is left alone.

The per-request choice is stored in the coroutine context
(`RequestAttributes::RequestContentStrategy`) rather than on the strategy
context object. There is no DI container: providers build everything once at
`WorkerStart`, so a controller holds the same collaborator for every request that
worker serves, and two concurrent requests would otherwise overwrite each other's
choice.

## Consequences

`src/API/Fbs/` now holds *nothing but* generated code, so that directory and the
PHPStan baseline are finally the same set.

The file count roughly doubled for messages: 39 proxies became 39 DTOs plus 39
factories. That is the price of the split, and it buys files that each do one
thing.

Nothing is inherited and nothing is shared. A trait supplied the boilerplate
half of the outbound factory at first, and a trait was the wrong tool: the class
named in `implements` was not the class fulfilling the contract, and a reader
following `IResponseAbstractFactory` landed nowhere. The JSON coercion helpers
went the other way for the same reason — `CoercesJson` was a trait supplying
behaviour a factory *uses* rather than behaviour a factory *is*, and is now the
static `Interno\JsonHelper`.

An earlier round of this had a public `buildInto()` beside the contract, for a
parent to nest a child through. It was the wrong shape twice over — a method
outside the contract is a method callers bind to concretely — and handing the
builder to `createFlatbuffer()` removed the need for it entirely.


Adding an endpoint now means writing two classes per message instead of one. The
`dev` skill's mirror files point at `LoginXRequestFactory` and `UserXFactory`,
which are the smallest complete examples of each direction.

A client that sends `Accept: application/x-flatbuffers` and parses errors as
JSON breaks. The published front end asks for JSON, so nothing in this
repository's tree was affected — but it is a contract change, and `swagger.json`
has to grow `application/x-flatbuffers` on its error responses to match.

The controllers gained one or two constructor parameters each, and four lines
around every decode and every render. That is the visible cost of not letting
them reach for a global, and of leaving the status decision where the request is
understood.

## Revisit if

A third wire format appears that is not a simple encoding of the same tables —
something needing per-message decisions rather than a per-format technique. The
strategy side stops paying for itself the moment a strategy has to know which
message it is looking at.
