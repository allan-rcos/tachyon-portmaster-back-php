<?php

/**
 * Metadata Controller.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IMetadataController;
use API\Controllers\ResolvesCaller;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Metadata\MetadataItemXResponse;
use API\Negociation\DTO\Metadata\PermissionListXResponse;
use API\Negociation\DTO\Metadata\PermissionListXResponseFactory;
use API\Negociation\IAcceptsStrategy;
use App\Queries\Role\ListPermissionsQuery;
use App\Services\IListPermissionsUseCase;
use Domain\Models\IPermission;
use Ds\Seq;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * System metadata catalogue endpoints.
 *
 * Answers with a bare `data` array — no `next_cursor`, no `total`. See
 * {@see \App\Queries\Role\ListPermissionsQuery} for why this listing is
 * the one that is not paged.
 *
 * @see IMetadataController The contract this implements.
 * @see ProductController The action shape this follows.
 * @uses IListPermissionsUseCase Reads the permission catalogue.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class MetadataController implements IMetadataController
{
    use ResolvesCaller;

    /**
     * @param  IListPermissionsUseCase  $listPermissions  Backs
     *                                                    {@see listPermissions()}.
     * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IListPermissionsUseCase $listPermissions,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * Renders the permission catalogue.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `PermissionListXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function listPermissions(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }

        $result = $this->listPermissions->execute(new ListPermissionsQuery(
            context: $caller->getValue(),
            search: $this->search($request),
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var Seq<IPermission> $items */
        $items = $result->getValue();

        $data = [];
        foreach ($items as $item) {
            $data[] = new MetadataItemXResponse(id: $item->id, slug: $item->slug);
        }

        $response = ApiResponse::body($this->accepts, new PermissionListXResponseFactory(new PermissionListXResponse(data: $data)));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * The `search` query parameter, if one was sent as a string.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ?string The raw term; null when absent, so the use case answers
     *                 with the whole catalogue.
     *
     * @copyright 2026 Tachyon
     */
    private function search(ServerRequestInterface $request): ?string
    {
        $params = $request->getQueryParams();

        return isset($params['search']) && is_string($params['search']) ? $params['search'] : null;
    }
}
