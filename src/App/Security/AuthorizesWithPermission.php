<?php

declare(strict_types=1);

namespace App\Security;

use App\Commands\Permission\RegisterPermissionCommand;
use App\Context\UserContext;
use App\Services\IRegisterPermissionUseCase;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Gives a use case the two halves of owning its permission: declaring it at boot
 * and enforcing it per call.
 *
 * A permission belongs to exactly one use case, so the use case is the only
 * sensible place to name it. {@see declarePermission()} runs from the
 * constructor at WorkerStart — the "logo na criação" moment — and keeps only the
 * slug; {@see authorize()} is the first line of `execute()`.
 *
 * The 403 is produced here, as a plain failed {@see Result}. The API layer never
 * decides it, it only maps `LeafContext->code` onto the response.
 */
trait AuthorizesWithPermission
{
    /**
     * The slug returned by the registry; the sole thing the use case retains.
     *
     * `readonly` so that a `readonly` use case may use this trait at all — PHP
     * rejects a mutable trait property in a readonly class.
     */
    private readonly string $permission;

    /**
     * Declares this use case's permission and returns its slug, to be assigned
     * straight to {@see $permission} in the constructor:
     *
     * ```php
     * $this->permission = $this->declarePermission($registrar, 'product:create');
     * ```
     *
     * It returns rather than assigns because a `readonly` property may only be
     * written in the declaring class's constructor — an assignment made inside
     * this method would be rejected.
     *
     * The slug is all a permission is: see {@see \Domain\Models\IPermission} for
     * why it carries no label or description.
     */
    private function declarePermission(
        IRegisterPermissionUseCase $registrar,
        string $slug,
    ): string {
        return $registrar->execute(new RegisterPermissionCommand($slug));
    }

    /**
     * Guards the use case. Returns a successful void {@see Result} when the
     * caller may proceed, or the 403 failure to hand straight back:
     *
     * ```php
     * $authorized = $this->authorize($command->context);
     * if (!$authorized->isSuccess()) {
     *     return Result::failure($authorized->getErrorId());
     * }
     * ```
     *
     * @return Result<null>
     */
    private function authorize(UserContext $context): Result
    {
        if ($context->hasPermission($this->permission)) {
            return Result::void();
        }

        return Result::failure(Leaf::newError(new LeafContext(
            message: 'You do not have permission to perform this action.',
            code: 403,
        )));
    }
}
