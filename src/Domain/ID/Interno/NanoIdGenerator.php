<?php

declare(strict_types=1);

namespace Domain\ID\Interno;

use Domain\ID\IRandomIdGenerator;
use Hidehalo\Nanoid\Client;

/**
 * {@see IRandomIdGenerator} backed by NanoID (hidehalo/nanoid-php).
 *
 * A cryptographically-random, URL-safe string of {@see SIZE} characters. At the
 * default 21 characters the collision profile matches UUIDv4 while staying
 * shorter and alphanumeric-friendly.
 *
 * This class is the only place that names the NanoID library: consumers depend
 * on {@see IRandomIdGenerator}, so the implementation is swappable here alone.
 */
final readonly class NanoIdGenerator implements IRandomIdGenerator
{
    private const int SIZE = 21;

    public function __construct(
        private Client $client = new Client(),
    ) {
    }

    public function generate(): string
    {
        return $this->client->generateId(self::SIZE, Client::MODE_DYNAMIC);
    }
}
