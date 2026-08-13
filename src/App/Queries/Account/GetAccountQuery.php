<?php

/**
 * Get Account Query.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Queries\Account;

use App\Context\UserContext;

/**
 * Reads the **caller's own** profile.
 *
 * Carries no id: the subject is always `$context->id`. Reading someone else's
 * profile is a separate, permission-guarded operation
 * ({@see \App\Queries\User\GetUserQuery}).
 *
 * @see \App\Services\IGetAccountUseCase What consumes it.
 * @see \App\Queries\User\GetUserQuery The administrative counterpart.
 * @see \App\Queries\Product\ListProductsQuery The query shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class GetAccountQuery
{
    /**
     * @param  UserContext  $context  The caller, who is also the subject. The
     *                                whole of the query.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
    ) {
    }
}
