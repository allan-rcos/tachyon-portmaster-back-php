<?php

/**
 * Update Account Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Account\UpdateAccountCommand;
use App\Services\IUpdateAccountUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Changes the caller's own profile.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}, minus the
 * authorization step and with the target taken from the context rather than the
 * command.
 *
 * That is the whole of the difference from {@see UpdateUserUseCase}: because a
 * caller can only ever reach their own record, there is nothing to check, so
 * this class does not use {@see \App\Security\AuthorizesWithPermission} and
 * takes no registrar.
 *
 * @see IUpdateAccountUseCase The contract this implements.
 * @see UpdateUserUseCase The guarded counterpart.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the caller and persists the new state.
 * @uses IUserTM Validates and rebuilds.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UpdateAccountUseCase implements IUpdateAccountUseCase
{
    /**
     * Takes no registrar: there is no permission to declare.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IUserRepository  $users  Read from and written to.
     * @param  IUserTM  $userTM  Validates and rebuilds the profile.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IUserTM $userTM,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(UpdateAccountCommand $command): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->users->findById($command->context->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        /** @var IUser $user */
        $user = $existing->getValue();

        $built = $this->userTM->update($user, $command->name, $command->email);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IUser $updated */
        $updated = $built->getValue();

        $persisted = $this->users->update($updated);
        if (!$persisted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($persisted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($updated);
    }
}
