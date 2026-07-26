<?php

declare(strict_types=1);

namespace API;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Factory surface of the presentation (HTTP) layer.
 *
 * Its single job is to hand back a ready-to-use PSR-15 request handler — the
 * full middleware stack terminating in route dispatch, with every controller
 * already wired. Built by {@see \API\ApiRegister::execute()}.
 */
interface IApiProvider
{
    public function router(): RequestHandlerInterface;
}
