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
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Account\AccountPasswordChangeXRequestFactory;
use API\Negociation\DTO\Account\AccountProfileXResponse;
use API\Negociation\DTO\Account\AccountProfileXResponseFactory;
use API\Negociation\DTO\Account\AccountUpdateXRequestFactory;
use API\Negociation\DTO\Account\RoleXResponse;
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IContentTypeStrategy;
use App\Commands\Account\ChangePasswordCommand;
use App\Commands\Account\UpdateAccountCommand;
use App\Context\UserContext;
use App\Queries\Account\GetAccountQuery;
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
     * @param  IContentTypeStrategy  $contentType  Decodes the request bodies.
             * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IGetAccountUseCase $getAccount,
        private IUpdateAccountUseCase $updateAccount,
        private IChangePasswordUseCase $changePassword,
        private IContentTypeStrategy $contentType,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * Renders the caller's own profile.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An `AccountProfileXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
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
     * @return ResponseInterface An `AccountProfileXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new AccountUpdateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->updateAccount->execute(new UpdateAccountCommand(
            context: $context,
            name: $body->name ?? '',
            email: $body->email ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
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
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new AccountPasswordChangeXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->changePassword->execute(new ChangePasswordCommand(
            context: $context,
            currentPassword: $body->currentPassword ?? '',
            newPassword: $body->newPassword ?? '',
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
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
     * @return ResponseInterface An `AccountProfileXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    private function profile(UserContext $context): ResponseInterface
    {
        $result = $this->getAccount->execute(new GetAccountQuery($context));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var AccountView $view */
        $view = $result->getValue();

        $roles = [];
        foreach ($view->roles as $role) {
            $roles[] = new RoleXResponse(
                id: $role->id,
                name: $role->name,
                userCount: $role->userCount,
                permissions: $role->permissions,
            );
        }

        $response = ApiResponse::body($this->accepts, new AccountProfileXResponseFactory(new AccountProfileXResponse(
            id: $view->id,
            name: $view->name,
            email: $view->email,
            roles: $roles,
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }
}
