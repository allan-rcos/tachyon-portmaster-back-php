<?php

declare(strict_types=1);

namespace API\Auth\Interno;

use API\Auth\IRefreshTokenService;
use API\Auth\ITokenService;
use API\Config\JwtConfig;
use App\Commands\Marker\RegisterMarkerGroupCommand;
use App\Commands\Marker\SetMarkerCommand;
use App\Context\UserContext;
use App\Queries\Marker\GetMarkerQuery;
use App\Services\IGetMarkerUseCase;
use App\Services\IRegisterMarkerGroupUseCase;
use App\Services\ISetMarkerUseCase;
use App\Services\IValidateSessionUseCase;
use Domain\Models\IUser;
use Infra\Database\IUnitOfWork;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * {@see IRefreshTokenService} over signed tokens plus a marker per token.
 *
 * The division of labour is the whole design: the **signature** answers "did we
 * issue this, and to whom", the **marker** answers "is it still live", and
 * {@see IValidateSessionUseCase} answers "does that user still exist". None of
 * the three can be dropped — a signature cannot be revoked, a marker knows
 * nothing about identity, and neither notices a deleted account.
 *
 * Every operation opens its own unit of work. That is not incidental: this
 * service runs *outside* any use case, called straight from the controller after
 * login has already committed, so nothing else has a boundary open for the
 * marker repository to enlist in.
 */
final readonly class RefreshTokenService implements IRefreshTokenService
{
    /** The marker group every refresh token is flagged in. */
    private const string GROUP = 'refresh-token';

    private string $group;

    public function __construct(
        private ITokenService $tokens,
        private IValidateSessionUseCase $validateSession,
        private ISetMarkerUseCase $setMarker,
        private IGetMarkerUseCase $getMarker,
        private IUnitOfWork $unitOfWork,
        private JwtConfig $config,
        IRegisterMarkerGroupUseCase $registrar,
    ) {
        // Declared at WorkerStart, the same moment guarded use cases declare
        // their permissions — so a server that cannot register the group refuses
        // to boot rather than failing every login later.
        $this->group = $registrar->execute(new RegisterMarkerGroupCommand(self::GROUP));
    }

    public function issue(IUser $user): Result
    {
        $token = $this->tokens->issueRefresh($user);

        // The marker outlives the token by exactly nothing: once the signature
        // has expired the flag is unreachable anyway, so the TTLs match.
        $marked = $this->inTransaction(fn (): Result => $this->setMarker->execute(new SetMarkerCommand(
            group: $this->group,
            value: $token,
            flag: true,
            ttlSeconds: $this->config->refreshTtlSeconds,
        )));

        if (!$marked->isSuccess()) {
            return Result::failure($marked->getErrorId());
        }

        return Result::success($token);
    }

    public function rotate(string $token): Result
    {
        $verified = $this->tokens->verifyRefresh($token);
        if (!$verified->isSuccess()) {
            return self::invalid();
        }

        /** @var UserContext $context */
        $context = $verified->getValue();

        $live = $this->inTransaction(fn (): Result => $this->getMarker->execute(
            new GetMarkerQuery($this->group, $token),
        ));

        if (!$live->isSuccess()) {
            return self::invalid();
        }

        // Anything but true means this token is spent: consumed by an earlier
        // refresh, revoked by a logout, or expired. All the same answer.
        if ($live->getValue() !== true) {
            return self::invalid();
        }

        $consumed = $this->consume($token);
        if (!$consumed->isSuccess()) {
            return Result::failure($consumed->getErrorId());
        }

        // Only now is the caller's identity re-checked: the token was ours and
        // unspent, but its claims are up to fourteen days old.
        $revalidated = $this->validateSession->execute($context);
        if (!$revalidated->isSuccess()) {
            return self::invalid();
        }

        /** @var IUser $user */
        $user = $revalidated->getValue();

        $replacement = $this->issue($user);
        if (!$replacement->isSuccess()) {
            return Result::failure($replacement->getErrorId());
        }

        /** @var string $issued */
        $issued = $replacement->getValue();

        return Result::success(['user' => $user, 'token' => $issued]);
    }

    public function revoke(string $token): Result
    {
        return $this->consume($token);
    }

    /**
     * Flips the token's marker to consumed. Idempotent by the marker's own
     * rules: consuming something already dead writes nothing and succeeds.
     *
     * @return Result<null>
     */
    private function consume(string $token): Result
    {
        return $this->inTransaction(fn (): Result => $this->setMarker->execute(new SetMarkerCommand(
            group: $this->group,
            value: $token,
            flag: false,
            ttlSeconds: $this->config->refreshTtlSeconds,
        )));
    }

    /**
     * Runs one marker operation inside its own boundary.
     *
     * @template TValue
     * @param  callable(): Result<TValue>  $operation
     * @return Result<TValue>
     */
    private function inTransaction(callable $operation): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $result = $operation();

        if (!$result->isSuccess()) {
            $this->unitOfWork->rollback();

            return $result;
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return $result;
    }

    /**
     * @return Result<never>
     */
    private static function invalid(): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'Invalid or expired refresh token.',
            code: 401,
        )));
    }
}
