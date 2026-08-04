<?php

/**
 * Product Controller Contract.
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
 * Product catalogue endpoints (`/products`).
 *
 * The **CRUD contract shape**, written out once here and referenced by the other
 * resource controllers. Every action takes the PSR-7 request and returns a
 * PSR-7 response; path variables arrive as request attributes; a body, where
 * there is one, is the matching `*RequestProxy` and the answer the matching
 * `*ResponseProxy`. Failures are RFC 7807 problem documents, so no action
 * declares a `@throws`.
 *
 * Which permission each action needs is the use case's to state, not this
 * contract's — see {@see \App\Services\IListProductsUseCase} and its siblings.
 *
 * @see \API\Controllers\Interno\ProductController The implementation.
 * @see \API\Http\Router\IVersionedRouter Where these methods are bound to paths.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IProductController
{
    /**
     * `GET /products` — a keyset page of products, filtered by `search`.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function list(ServerRequestInterface $request): ResponseInterface;

    /**
     * `POST /products` — registers a product. Answers 201.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(ServerRequestInterface $request): ResponseInterface;

    /**
     * `GET /products/{id}` — one product, or 404.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(ServerRequestInterface $request): ResponseInterface;

    /**
     * `PUT /products/{id}` — replaces a product's fields, or 404.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function update(ServerRequestInterface $request): ResponseInterface;

    /**
     * `DELETE /products/{id}` — removes a product. Answers 204.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function delete(ServerRequestInterface $request): ResponseInterface;
}
