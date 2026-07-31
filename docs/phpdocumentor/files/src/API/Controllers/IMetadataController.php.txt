<?php

/**
 * Metadata Controller Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * System metadata catalogues (`/metadata`).
 *
 * Read-only, by construction: the catalogue is filled from code at WorkerStart,
 * so there is nothing here a client could create or delete. It exists for
 * discovery — a permission is grantable because some use case declared it, so
 * the set is not knowable from the schema the way {@see \Domain\Enums\RiskClass}
 * or {@see \Domain\Enums\TelemetryEvent} are.
 *
 * @see IRoleAdminController Where the permission slugs listed here are granted.
 * @see IProductController The contract shape these follow.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMetadataController
{
    /**
     * `GET /metadata/permissions` — the whole permission catalogue.
     *
     * Unpaged: `?search=` narrows it, but there is no cursor and no limit.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function listPermissions(ServerRequestInterface $request): ResponseInterface;
}
