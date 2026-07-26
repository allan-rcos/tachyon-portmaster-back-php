<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IAccountController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Account\AccountPasswordChangeRequestProxy;
use API\Fbs\Account\AccountProfileResponseProxy;
use API\Fbs\Account\AccountUpdateRequestProxy;
use API\Fbs\Account\RoleResponseProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Commands\Account\ChangePasswordCommand;
use App\Context\UserContext;
use App\Queries\Account\GetAccountQuery;
use App\Commands\Account\UpdateAccountCommand;
use App\Services\IChangePasswordUseCase;
use App\Services\IGetAccountUseCase;
use App\Services\IUpdateAccountUseCase;
use Infra\Query\Account\AccountView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AccountController implements IAccountController
{
    use ResolvesCaller;

    public function __construct(
        private IGetAccountUseCase $getAccount,
        private IUpdateAccountUseCase $updateAccount,
        private IChangePasswordUseCase $changePassword,
    ) {
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        return $this->profile($context);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = AccountUpdateRequestProxy::fromStream($request->getBody());
        $result = $this->updateAccount->execute(new UpdateAccountCommand(
            context: $context,
            name: $body->name ?? '',
            email: $body->email ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return $this->profile($context);
    }

    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = AccountPasswordChangeRequestProxy::fromStream($request->getBody());
        $result = $this->changePassword->execute(new ChangePasswordCommand(
            context: $context,
            currentPassword: $body->currentPassword ?? '',
            newPassword: $body->newPassword ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    private function profile(UserContext $context): ResponseInterface
    {
        $result = $this->getAccount->execute(new GetAccountQuery($context));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var AccountView $view */
        $view = $result->getValue();

        $roles = [];
        foreach ($view->roles as $role) {
            $roles[] = new RoleResponseProxy(
                id: $role->id,
                name: $role->name,
                userCount: $role->userCount,
                permissions: $role->permissions,
            );
        }

        return ApiResponse::body(new AccountProfileResponseProxy(
            id: $view->id,
            name: $view->name,
            email: $view->email,
            roles: $roles,
        ));
    }
}
