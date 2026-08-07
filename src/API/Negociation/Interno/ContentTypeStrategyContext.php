<?php

/**
 * Content Type Strategy Context.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\Interno;

use API\Http\RequestAttributes;
use API\Negociation\IContentTypeStrategy;
use API\Negociation\IContentTypeStrategyManager;
use API\Negociation\IRequestAbstractFactory;
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Result;

/**
 * The inbound strategy of *this* request, behind an interface that never
 * changes.
 *
 * There is no DI container here: providers build everything once, at
 * `WorkerStart`, and a controller therefore holds the same collaborator for
 * every request that worker ever serves. A strategy chosen per request cannot
 * be a property of such an object — two requests being served concurrently in
 * one worker would overwrite each other's choice.
 *
 * So the context is the stable object and the *choice* lives in the coroutine
 * context, which under OpenSwoole is per-request by construction. The middleware
 * writes through {@see IContentTypeStrategyManager}; the controllers read
 * through {@see IContentTypeStrategy}; neither sees the other's half, and only
 * {@see \API\Interno\ApiProvider} knows they are one object.
 *
 * The strategies themselves hold no state, so there is exactly one of each per
 * worker too: what travels per request is the reference, never a new object.
 *
 * @see AcceptsStrategyContext The outbound twin.
 * @see RequestAttributes The per-coroutine storage the choice lives in.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ContentTypeStrategyContext implements IContentTypeStrategy, IContentTypeStrategyManager
{
    /**
     * @param  IContentTypeStrategy  $fallback  Used when nothing was recorded;
     *                                          binary, mirroring
     *                                          {@see \API\Http\ContentKind::fromContentType()}'s
     *                                          own default.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private IContentTypeStrategy $fallback)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param  IContentTypeStrategy  $strategy  Resolved from `Content-Type`.
     *
     * @copyright 2026 Tachyon
     */
    public function setStrategy(IContentTypeStrategy $strategy): void
    {
        RequestAttributes::RequestContentStrategy->write($strategy);
    }

    /**
     * {@inheritDoc}
     *
     * The fallback is reached only when the negotiation middleware did not run
     * — outside a coroutine, or in a test that exercises a controller directly.
     *
     * @template T of object
     *
     * @param  StreamInterface  $body  The request body.
     * @param  IRequestAbstractFactory<T>  $factory  Builds the message once the
     *                                               body has been decoded.
     * @return Result<T> The hydrated message, or the failure.
     *
     * @copyright 2026 Tachyon
     */
    public function execute(StreamInterface $body, IRequestAbstractFactory $factory): Result
    {
        $strategy = RequestAttributes::RequestContentStrategy->read();

        if (!$strategy instanceof IContentTypeStrategy) {
            $strategy = $this->fallback;
        }

        return $strategy->execute($body, $factory);
    }
}
