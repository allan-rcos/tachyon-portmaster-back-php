<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IUserAdminController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Account\RoleResponseProxy;
use API\Fbs\Admin\UserAdminPasswordResetRequestProxy;
use API\Fbs\Admin\UserAdminResponseProxy;
use API\Fbs\Admin\UserListResponseProxy;
use API\Fbs\Admin\UserCreateRequestProxy;
use API\Fbs\Admin\UserUpdateRequestProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Commands\User\CreateUserCommand;
use App\Commands\User\ResetUserPasswordCommand;
use App\Commands\User\UpdateUserCommand;
use App\Commands\User\UpdateUserRolesCommand;
use App\Commands\User\DeleteUserCommand;
use App\Context\UserContext;
use App\Queries\User\GetUserQuery;
use App\Queries\User\ListUsersQuery;
use App\Services\ICreateUserUseCase;
use App\Services\IDeleteUserUseCase;
use App\Services\IGetUserUseCase;
use App\Services\IListUsersUseCase;
use App\Services\IResetUserPasswordUseCase;
use App\Services\IUpdateUserRolesUseCase;
use App\Services\IUpdateUserUseCase;
use Domain\Models\IUser;
use Infra\Query\Account\AccountView;
use Infra\Query\User\UserListView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UserAdminController implements IUserAdminController
{
    use ResolvesCaller;

    public function __construct(
        private IListUsersUseCase $listUsers,
        private ICreateUserUseCase $createUser,
        private IGetUserUseCase $getUser,
        private IUpdateUserUseCase $updateUser,
        private IDeleteUserUseCase $deleteUser,
        private IResetUserPasswordUseCase $resetPassword,
        private IUpdateUserRolesUseCase $updateUserRoles,
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
        $result = $this->listUsers->execute(new ListUsersQuery(
            context: $context,
            page: isset($params['page']) && is_numeric($params['page']) ? (int) $params['page'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var UserListView $view */
        $view = $result->getValue();

        $users = [];
        foreach ($view->items as $item) {
            $users[] = $this->response($item);
        }

        return ApiResponse::body(new UserListResponseProxy(data: $users));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = UserCreateRequestProxy::fromStream($request->getBody());
        $result = $this->createUser->execute(new CreateUserCommand(
            context: $context,
            name: $body->name ?? '',
            email: $body->email ?? '',
            initialPassword: $body->initialPassword ?? '',
            roleIds: $body->roleIds,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IUser $user */
        $user = $result->getValue();

        return $this->respondWithUser($context, $user->id, 201);
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        return $this->respondWithUser($context, $this->pathId($request), 200);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = UserUpdateRequestProxy::fromStream($request->getBody());
        $result = $this->updateUser->execute(new UpdateUserCommand(
            context: $context,
            id: $this->pathId($request),
            name: $body->name ?? '',
            email: $body->email ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IUser $user */
        $user = $result->getValue();

        return $this->respondWithUser($context, $user->id, 200);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->deleteUser->execute(new DeleteUserCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    public function resetPassword(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = UserAdminPasswordResetRequestProxy::fromStream($request->getBody());
        $result = $this->resetPassword->execute(new ResetUserPasswordCommand(
            context: $context,
            id: $this->pathId($request),
            newPassword: $body->newPassword ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    public function updateRoles(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->updateUserRoles->execute(new UpdateUserRolesCommand(
            context: $context,
            id: $this->pathId($request),
            roleIds: $this->roleIdsFromBody($request),
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IUser $user */
        $user = $result->getValue();

        return $this->respondWithUser($context, $user->id, 200);
    }

    /**
     * Re-reads the user's full profile (with roles) through the query side.
     */
    private function respondWithUser(UserContext $context, string $id, int $status): ResponseInterface
    {
        $result = $this->getUser->execute(new GetUserQuery($context, $id));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var AccountView $view */
        $view = $result->getValue();

        return ApiResponse::body($this->response($view), $status);
    }

    private function response(AccountView $view): UserAdminResponseProxy
    {
        $roles = [];
        foreach ($view->roles as $role) {
            $roles[] = new RoleResponseProxy(
                id: $role->id,
                name: $role->name,
                userCount: $role->userCount,
                permissions: $role->permissions,
            );
        }

        return new UserAdminResponseProxy(
            id: $view->id,
            name: $view->name,
            email: $view->email,
            roles: $roles,
        );
    }

    /**
     * Parses the inline `{ "role_ids": ["base62", ...] }` body (no FlatBuffers schema).
     *
     * @return list<string>
     */
    private function roleIdsFromBody(ServerRequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true);
        $raw = is_array($decoded) && isset($decoded['role_ids']) && is_array($decoded['role_ids'])
            ? $decoded['role_ids']
            : [];

        $ids = [];
        foreach ($raw as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

}
