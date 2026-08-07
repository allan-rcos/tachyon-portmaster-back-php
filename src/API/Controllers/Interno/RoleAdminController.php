<?php

/**
 * Role Admin Controller.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IRoleAdminController;
use API\Controllers\ResolvesCaller;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Account\RoleXResponse;
use API\Negociation\DTO\Account\RoleXResponseFactory;
use API\Negociation\DTO\Admin\RoleCreateXRequestFactory;
use API\Negociation\DTO\Admin\RoleListXResponse;
use API\Negociation\DTO\Admin\RoleListXResponseFactory;
use API\Negociation\DTO\Admin\RolePermissionsUpdateXRequestFactory;
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IContentTypeStrategy;
use App\Commands\Role\CreateRoleCommand;
use App\Commands\Role\UpdateRolePermissionsCommand;
use App\Context\UserContext;
use App\Queries\Role\GetRoleQuery;
use App\Queries\Role\ListRolesQuery;
use App\Services\ICreateRoleUseCase;
use App\Services\IGetRoleUseCase;
use App\Services\IListRolesUseCase;
use App\Services\IUpdateRolePermissionsUseCase;
use Domain\Models\IRole;
use Infra\Query\Role\RoleListView;
use Infra\Query\Role\RoleViewItem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Role administration endpoints.
 *
 * Both writes answer by re-reading through the query side rather than rendering
 * what the write returned — see {@see respondWithRole()} for why.
 *
 * @see IRoleAdminController The contract this implements.
 * @see ProductController The action shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class RoleAdminController implements IRoleAdminController
{
    use ResolvesCaller;

    /**
     * @param  IListRolesUseCase  $listRoles  Backs {@see list()}.
     * @param  ICreateRoleUseCase  $createRole  Backs {@see create()}.
     * @param  IUpdateRolePermissionsUseCase  $updatePermissions  Backs
     *                                                            {@see syncPermissions()}.
     * @param  IGetRoleUseCase  $getRole  Re-reads a role after either write.
     * @param  IContentTypeStrategy  $contentType  Decodes the request bodies.
             * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IListRolesUseCase $listRoles,
        private ICreateRoleUseCase $createRole,
        private IUpdateRolePermissionsUseCase $updatePermissions,
        private IGetRoleUseCase $getRole,
        private IContentTypeStrategy $contentType,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * Renders a page of roles.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `RoleListXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $params = $request->getQueryParams();
        $result = $this->listRoles->execute(new ListRolesQuery(
            context: $context,
            cursor: isset($params['cursor']) && is_string($params['cursor']) ? $params['cursor'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
            search: isset($params['search']) && is_string($params['search']) ? $params['search'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var RoleListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->response($item);
        }

        $response = ApiResponse::body($this->accepts, new RoleListXResponseFactory(new RoleListXResponse(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Registers a role with its initial permissions.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `RoleXResponse` with 201, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new RoleCreateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->createRole->execute(new CreateRoleCommand(
            context: $context,
            name: $body->name ?? '',
            permissions: $body->permissions,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IRole $role */
        $role = $result->getValue();

        return $this->respondWithRole($context, $role->id, 201);
    }

    /**
     * Replaces the role's whole permission set with the one presented.
     *
     * A replacement, not a merge: a permission left out of the body is revoked.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `RoleXResponse`, or a problem document —
     *                           404 when nothing matches the id.
     *
     * @copyright 2026 Tachyon
     */
    public function syncPermissions(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new RolePermissionsUpdateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();

        $result = $this->updatePermissions->execute(new UpdateRolePermissionsCommand(
            context: $context,
            id: $this->pathId($request),
            permissions: $body->permissions,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IRole $role */
        $role = $result->getValue();

        return $this->respondWithRole($context, $role->id, 200);
    }

    /**
     * Re-reads the role through the query side so the response carries the fresh
     * user_count (a read aggregate the write model does not track).
     *
     * @param  UserContext  $context  The caller, forwarded to the query.
     * @param  string  $id  Role to read back.
     * @param  int  $status  201 after a create, 200 after an update.
     * @return ResponseInterface A `RoleXResponse`, or a problem document.
     *
     * @copyright 2026 Tachyon
     */
    private function respondWithRole(UserContext $context, string $id, int $status): ResponseInterface
    {
        $view = $this->getRole->execute(new GetRoleQuery($context, $id));
        if (!$view->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $view);
        }

        /** @var RoleViewItem $item */
        $item = $view->getValue();

        $response = ApiResponse::body($this->accepts, new RoleXResponseFactory($this->response($item)), $status);

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * The response message for one role.
     *
     * @param  RoleViewItem  $item  One row of a role query.
     * @return RoleXResponse Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function response(RoleViewItem $item): RoleXResponse
    {
        return new RoleXResponse(
            id: $item->id,
            name: $item->name,
            userCount: $item->userCount,
            permissions: $item->permissions,
        );
    }


}
