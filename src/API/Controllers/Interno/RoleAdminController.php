<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IRoleAdminController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Account\RoleResponseProxy;
use API\Fbs\Admin\RoleCreateRequestProxy;
use API\Fbs\Admin\RoleListResponseProxy;
use API\Fbs\Admin\RolePermissionsUpdateRequestProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
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

final readonly class RoleAdminController implements IRoleAdminController
{
    use ResolvesCaller;

    public function __construct(
        private IListRolesUseCase $listRoles,
        private ICreateRoleUseCase $createRole,
        private IUpdateRolePermissionsUseCase $updatePermissions,
        private IGetRoleUseCase $getRole,
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
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
            return ProblemResponse::fromResult($result);
        }

        /** @var RoleListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->response($item);
        }

        return ApiResponse::body(new RoleListResponseProxy(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        ));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = RoleCreateRequestProxy::fromStream($request->getBody());
        $result = $this->createRole->execute(new CreateRoleCommand(
            context: $context,
            name: $body->name ?? '',
            permissions: $body->permissions,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IRole $role */
        $role = $result->getValue();

        return $this->respondWithRole($context, $role->id, 201);
    }

    public function syncPermissions(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->updatePermissions->execute(new UpdateRolePermissionsCommand(
            context: $context,
            id: $this->pathId($request),
            permissions: RolePermissionsUpdateRequestProxy::fromStream($request->getBody())->permissions,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IRole $role */
        $role = $result->getValue();

        return $this->respondWithRole($context, $role->id, 200);
    }

    /**
     * Re-reads the role through the query side so the response carries the fresh
     * user_count (a read aggregate the write model does not track).
     */
    private function respondWithRole(UserContext $context, string $id, int $status): ResponseInterface
    {
        $view = $this->getRole->execute(new GetRoleQuery($context, $id));
        if (!$view->isSuccess()) {
            return ProblemResponse::fromResult($view);
        }

        /** @var RoleViewItem $item */
        $item = $view->getValue();

        return ApiResponse::body($this->response($item), $status);
    }

    private function response(RoleViewItem $item): RoleResponseProxy
    {
        return new RoleResponseProxy(
            id: $item->id,
            name: $item->name,
            userCount: $item->userCount,
            permissions: $item->permissions,
        );
    }


}
