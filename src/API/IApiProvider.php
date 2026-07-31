<?php

/**
 * API Provider Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Factory surface of the presentation (HTTP) layer.
 *
 * Its single job is to hand back a ready-to-use PSR-15 request handler — the
 * full middleware stack terminating in route dispatch, with every controller
 * already wired. Built by {@see \API\ApiRegister::execute()}.
 *
 * Deliberately one method wide. The layers below expose a factory per type,
 * because their consumers pick and choose; nothing outside this layer wants an
 * individual controller or middleware, only the assembled handler.
 *
 * @see ApiRegister What builds it.
 * @see \API\Interno\ApiProvider The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IApiProvider
{
    /**
     * The assembled request handler.
     *
     * @return RequestHandlerInterface The middleware stack, terminating in route
     *                                 dispatch.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function router(): RequestHandlerInterface;
}
