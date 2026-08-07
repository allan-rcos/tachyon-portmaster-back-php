<?php

/**
 * Accepts Strategy Context.
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
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IAcceptsStrategyManager;
use API\Negociation\IResponseAbstractFactory;
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Result;

/**
 * The outbound strategy of *this* request, behind an interface that never
 * changes.
 *
 * Same construction as {@see ContentTypeStrategyContext}, and for the same
 * reason — see that class for why the choice cannot be a property.
 *
 * @see ContentTypeStrategyContext The inbound twin, where the rationale is written out.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class AcceptsStrategyContext implements IAcceptsStrategy, IAcceptsStrategyManager
{
    /**
     * @param  IAcceptsStrategy  $fallback  Used when nothing was recorded. JSON,
     *                                      mirroring {@see \API\Http\ContentKind::fromAccept()}'s
     *                                      own default, and it matters most
     *                                      exactly where it is reached: the
     *                                      recoverer sits *outside* the
     *                                      negotiation middleware, so a failure
     *                                      raised before negotiation ran still
     *                                      has to answer something — and JSON is
     *                                      the format any client can read.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private IAcceptsStrategy $fallback)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param  IAcceptsStrategy  $strategy  Resolved from `Accept`.
     *
     * @copyright 2026 Tachyon
     */
    public function setStrategy(IAcceptsStrategy $strategy): void
    {
        RequestAttributes::ResponseAcceptsStrategy->write($strategy);
    }

    /**
     * {@inheritDoc}
     *
     * @param  IResponseAbstractFactory  $factory  Wraps the message to render.
     * @return Result<StreamInterface> The response body.
     *
     * @copyright 2026 Tachyon
     */
    public function toStream(IResponseAbstractFactory $factory): Result
    {
        return $this->strategy()->toStream($factory);
    }

    /**
     * This request's strategy, or the fallback when none was recorded.
     *
     * @return IAcceptsStrategy The strategy to render with.
     *
     * @copyright 2026 Tachyon
     */
    private function strategy(): IAcceptsStrategy
    {
        $strategy = RequestAttributes::ResponseAcceptsStrategy->read();

        return $strategy instanceof IAcceptsStrategy ? $strategy : $this->fallback;
    }
}
