<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Marker\RegisterMarkerGroupCommand;
use App\Services\IRegisterMarkerGroupUseCase;
use Domain\Models\IMarkerGroup;
use Domain\TableModules\IMarkerGroupTM;
use Infra\Repository\IMarkerGroupRepository;
use RuntimeException;
use Shared\Exceptions\Leaf;

/**
 * Encapsulates the marker-group declaration handshake, mirroring
 * {@see RegisterPermissionUseCase}: the domain table module validates and builds
 * the group, then the infra registry stores it and hands back its index.
 */
final readonly class RegisterMarkerGroupUseCase implements IRegisterMarkerGroupUseCase
{
    public function __construct(
        private IMarkerGroupTM $markerGroupTM,
        private IMarkerGroupRepository $groups,
    ) {
    }

    public function execute(RegisterMarkerGroupCommand $command): string
    {
        $built = $this->markerGroupTM->create($command->slug);

        if (!$built->isSuccess()) {
            throw new RuntimeException($this->reason($built->getErrorId(), $command->slug));
        }

        /** @var IMarkerGroup $group */
        $group = $built->getValue();

        $added = $this->groups->add($group);

        if (!$added->isSuccess()) {
            throw new RuntimeException($this->reason($added->getErrorId(), $command->slug));
        }

        /** @var IMarkerGroup $registered */
        $registered = $added->getValue();

        return $registered->slug;
    }

    private function reason(int $errorId, string $slug): string
    {
        $context = Leaf::getError($errorId);
        $message = $context !== null ? $context->message : 'unknown error';
        $details = $context?->details?->toArray() ?? [];

        return sprintf(
            'Cannot register marker group "%s": %s%s',
            $slug,
            $message,
            $details === [] ? '' : ' ('.json_encode($details).')',
        );
    }
}
