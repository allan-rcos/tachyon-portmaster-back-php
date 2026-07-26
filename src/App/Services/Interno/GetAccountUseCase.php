<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Account\GetAccountQuery;
use App\Services\IGetAccountUseCase;
use Infra\Query\Account\AccountView;
use Infra\Query\Interno\GetAccountDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class GetAccountUseCase implements IGetAccountUseCase
{
    public function __construct(
        private IQueryRepository $queries,
    ) {
    }

    public function execute(GetAccountQuery $query): Result
    {
        $result = $this->queries->run(new GetAccountDQL($query->context->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $view = $result->getValue();
        if (!$view instanceof AccountView) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Account not found',
                code: 404,
            )));
        }

        return Result::success($view);
    }
}
