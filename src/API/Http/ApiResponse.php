<?php

declare(strict_types=1);

namespace API\Http;

use API\Fbs\Contracts\IFbsProxy;
use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds successful API responses from a FlatBuffer proxy, honouring the
 * response {@see ContentKind} negotiated for the request.
 *
 * Controllers stay oblivious to json-vs-binary: they hand a proxy here and the
 * body is serialized via {@see IFbsProxy::toStream()} with the matching
 * `Content-Type`. Errors go through {@see ProblemResponse} instead.
 *
 * There is deliberately **no** "plain JSON" escape hatch. Every response body
 * must be a FlatBuffers table declared in `swagger/flatbuffers/schemas` and
 * published in `swagger.json` — an endpoint answering with an ad-hoc structure
 * is an endpoint no client can discover.
 */
final class ApiResponse
{
    /**
     * A response carrying the proxy as its (negotiated) body.
     */
    public static function body(IFbsProxy $proxy, int $status = 200): ResponseInterface
    {
        $kind = RequestAttributes::ResponseContentKind->read();
        $mediaType = $kind instanceof ContentKind ? $kind->mediaType() : MediaType::Json;

        return new Response(
            (string) $proxy->toStream(),
            $status,
            '',
            [HttpHeader::ContentType->value => $mediaType->value],
        );
    }

    /**
     * An empty `204 No Content` response.
     */
    public static function noContent(): ResponseInterface
    {
        return new Response('', 204);
    }
}
