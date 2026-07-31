<?php

/**
 * Leaf Context.
 *
 * @category Shared
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Shared\Exceptions;

use Ds\Map;
use JsonSerializable;

/**
 * What a failure actually says: a message, optional per-field details, and the
 * HTTP status it should become.
 *
 * Every failed {@see Result} carries an id; this is what that id resolves to.
 * Deciding the status code here rather than at the edge is deliberate — a table
 * module knows a broken rule is a 422 and a use case knows a conflicting state
 * is a 409, whereas a controller would have to infer it. The API layer only
 * reads {@see $code} back out.
 *
 * @see Leaf The registry this is stored in.
 * @see Result What carries the id.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LeafContext implements JsonSerializable
{
    /**
     * @param  string  $message  Human-readable description, safe to return to a
     *                           client — it is serialised into the response.
     * @param  ?Map<string, string>  $details  Field name to error, for validation
     *                                         failures concerning more than one field.
     * @param  int  $code  HTTP status this failure becomes: 422 for a broken
     *                     rule, 409 for a conflicting state, 404 for a missing
     *                     one, 403 for a denied permission, 500 for infrastructure.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $message,
        public ?Map $details = null, // Map<string, string>
        public int $code = 0,
    ) {
    }

    /**
     * Flattens the context for the response body, unwrapping {@see $details}
     * from the `Ds\Map` no JSON encoder would know what to do with.
     *
     * @return array<string, mixed> The `message`, `details` and `code` keys.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'details' => $this->details?->toArray(),
            'code' => $this->code
        ];
    }
}
