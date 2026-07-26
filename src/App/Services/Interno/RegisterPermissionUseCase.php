<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Permission\RegisterPermissionCommand;
use App\Services\IRegisterPermissionUseCase;
use Domain\Models\IPermission;
use Domain\TableModules\IPermissionTM;
use Infra\Repository\IPermissionRepository;
use RuntimeException;
use Shared\Exceptions\Leaf;

/**
 * Encapsulates the permission-declaration handshake so no other use case has to
 * know it: the domain table module validates and builds the permission, then the
 * infra registry stores it and hands back its index.
 */
final readonly class RegisterPermissionUseCase implements IRegisterPermissionUseCase
{
    public function __construct(
        private IPermissionTM $permissionTM,
        private IPermissionRepository $permissions,
    ) {
    }

    public function execute(RegisterPermissionCommand $command): string
    {
        $built = $this->permissionTM->create($command->slug);

        if (!$built->isSuccess()) {
            throw new RuntimeException($this->reason($built->getErrorId(), $command->slug));
        }

        /** @var IPermission $permission */
        $permission = $built->getValue();

        $added = $this->permissions->add($permission);

        if (!$added->isSuccess()) {
            throw new RuntimeException($this->reason($added->getErrorId(), $command->slug));
        }

        /** @var IPermission $registered */
        $registered = $added->getValue();

        return $registered->slug;
    }

    private function reason(int $errorId, string $slug): string
    {
        $context = Leaf::getError($errorId);
        $message = $context !== null ? $context->message : 'unknown error';
        $details = $context?->details?->toArray() ?? [];

        return sprintf(
            'Cannot register permission "%s": %s%s',
            $slug,
            $message,
            $details === [] ? '' : ' ('.json_encode($details).')',
        );
    }
}
