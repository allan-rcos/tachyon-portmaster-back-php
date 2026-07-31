<?php

/**
 * Account Controller.
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

/**
 * Self-service account endpoints.
 *
 * The subject is always the caller, so no action reads a path id: the
 * {@see UserContext} that authorizes the request also identifies the row it
 * acts on.
 *
 * @see IAccountController The contract this implements.
 * @see ProductController The action shape this follows.
 * @uses IGetAccountUseCase Reads the profile.
 * @uses IUpdateAccountUseCase Changes name and email.
 * @uses IChangePasswordUseCase Changes the password.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class AccountController implements IAccountController
{
    use ResolvesCaller;

    /**
     * @param  IGetAccountUseCase  $getAccount  Backs {@see get()} and the reread
     *                                          after an update.
     * @param  IUpdateAccountUseCase  $updateAccount  Backs {@see update()}.
     * @param  IChangePasswordUseCase  $changePassword  Backs
     *                                                  {@see changePassword()}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IGetAccountUseCase $getAccount,
        private IUpdateAccountUseCase $updateAccount,
        private IChangePasswordUseCase $changePassword,
    ) {
    }

    /**
     * Renders the caller's own profile.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An `AccountProfileResponseProxy`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        return $this->profile($context);
    }

    /**
     * Updates the caller's own name and email, then renders the result.
     *
     * The profile is re-read rather than assembled from the command, because
     * the response carries the caller's roles and those are not part of what
     * was just changed.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An `AccountProfileResponseProxy`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Changes the caller's own password, which requires the current one.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An empty 204, or a problem document.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Reads and renders the caller's profile.
     *
     * Shared by {@see get()} and {@see update()}, which answer with the same
     * document.
     *
     * @param  UserContext  $context  The caller.
     * @return ResponseInterface An `AccountProfileResponseProxy`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
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
