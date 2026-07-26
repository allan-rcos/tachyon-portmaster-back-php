<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\User\GetUserQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IGetUserUseCase;
use App\Services\IRegisterPermissionUseCase;
use Infra\Query\Account\AccountView;
use Infra\Query\Interno\GetAccountDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Admin read of a user profile. Shares {@see GetAccountDQL} with
 * {@see GetAccountUseCase} — same projection, different authorization.
 */
final readonly class GetUserUseCase implements IGetUserUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:get');
    }

    public function execute(GetUserQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetAccountDQL($query->userId));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $view = $result->getValue();
        if (!$view instanceof AccountView) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'User not found',
                code: 404,
            )));
        }

        return Result::success($view);
    }
}
