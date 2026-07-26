<?php

namespace Domain\TableModules\Interno;

use Domain\Enums\ContainerStatus;
use Domain\Models\IContainer;
use Domain\Models\Internal\Container;
use Domain\TableModules\IContainerTM;
use Domain\ID\IDatabaseIdGenerator;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class ContainerTM implements IContainerTM
{
    private const int MAX_CODE_LENGTH = 255;
    private const float MIN_SEAL_FILL_RATIO = 0.10;

    public function __construct(
        private IDatabaseIdGenerator $idGenerator,
    ) {
    }

    public function create(string $code, float $maxCapacity): Result
    {
        $errors = $this->validate($code, $maxCapacity);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new Container(
            id: $this->idGenerator->generate(),
            code: $code,
            currentWeight: 0.0,
            maxCapacity: $maxCapacity,
            status: ContainerStatus::Empty,
        ));
    }

    public function update(IContainer $container, float $maxCapacity): Result
    {
        $errors = $this->validate($container->code, $maxCapacity);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new Container(
            id: $container->id,
            code: $container->code,
            currentWeight: $container->currentWeight,
            maxCapacity: $maxCapacity,
            status: $container->status,
        ));
    }

    public function seal(IContainer $container): Result
    {
        if ($container->status !== ContainerStatus::Loading) {
            return $this->conflict('Only a container in the loading state can be sealed.');
        }

        if ($container->currentWeight < self::MIN_SEAL_FILL_RATIO * $container->maxCapacity) {
            return $this->conflict('A container must be at least 10% full to be sealed.');
        }

        return Result::success($this->withStatus($container, ContainerStatus::Sealed));
    }

    public function dispatch(IContainer $container): Result
    {
        if ($container->status !== ContainerStatus::Sealed) {
            return $this->conflict('Only a sealed container can be dispatched.');
        }

        return Result::success($this->withStatus($container, ContainerStatus::InTransit));
    }

    private function withStatus(IContainer $container, ContainerStatus $status): Container
    {
        return new Container(
            id: $container->id,
            code: $container->code,
            currentWeight: $container->currentWeight,
            maxCapacity: $container->maxCapacity,
            status: $status,
        );
    }

    /**
     * @return Result<never>
     */
    private function conflict(string $message): Result
    {
        return Result::failure(Leaf::newError(new LeafContext($message, code: 409)));
    }

    /**
     * @return Map<string, string>
     */
    private function validate(string $code, float $maxCapacity): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        $trimmed = trim($code);
        if ($trimmed === '') {
            $errors->put('code', 'Code is required.');
        } elseif (strlen($code) > self::MAX_CODE_LENGTH) {
            $errors->put('code', 'Code must not exceed ' . self::MAX_CODE_LENGTH . ' characters.');
        }

        if ($maxCapacity <= 0) {
            $errors->put('max_capacity', 'Max capacity must be greater than zero.');
        }

        return $errors;
    }
}
