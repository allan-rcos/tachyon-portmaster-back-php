<?php

/**
 * Detailed Error Context Definition.
 *
 * A module defining mapped tracking validation responses values mapping structures execution.
 *
 * @category Shared\Exceptions
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @filesource
 */

namespace Shared\Exceptions;

use Ds\Map;
use JsonSerializable;

/**
 * Detailed error context payload tracking internal validation boundaries.
 *
 * Exposes explicit implementations wrapping validations output block structures setup.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
readonly class LeafContext implements JsonSerializable
{
    /**
     * Constructs generic map definitions structure.
     *
     * @param  string  $message  Main description to user.
     * @param  ?Map<string, string>  $details  Properties validation mapping.
     * @param  int  $code  Associated logical classification status equivalent layout.
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     */
    public function __construct(
        public string $message,
        public ?Map $details = null, // Map<string, string>
        public int $code = 0,
    ) {
    }

    /**
     * Serializes error structure details.
     *
     * @return array<string, mixed> Data context implementation notation block structured equivalent map.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
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