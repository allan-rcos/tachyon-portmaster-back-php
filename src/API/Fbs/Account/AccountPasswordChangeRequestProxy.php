<?php

declare(strict_types=1);

namespace API\Fbs\Account;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see AccountPasswordChangeRequest} table.
 */
final class AccountPasswordChangeRequestProxy extends AccountPasswordChangeRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $current = $this->currentPassword !== null ? $builder->createString($this->currentPassword) : 0;
        $new     = $this->newPassword !== null ? $builder->createString($this->newPassword) : 0;

        return AccountPasswordChangeRequest::createAccountPasswordChangeRequest($builder, $current, $new);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(AccountPasswordChangeRequest $table): static
    {
        return new static(
            currentPassword: $table->getCurrentPassword(),
            newPassword: $table->getNewPassword(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(AccountPasswordChangeRequest::getRootAsAccountPasswordChangeRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            currentPassword: self::jsonNullableString($data, 'current_password'),
            newPassword: self::jsonNullableString($data, 'new_password'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'current_password' => $this->currentPassword,
            'new_password'     => $this->newPassword,
        ];
    }

    public static function fromStream(StreamInterface $body): static
    {
        $raw = (string) $body;

        if (RequestAttributes::RequestContentKind->read() === ContentKind::Json) {
            $decoded = json_decode($raw, true);

            return static::jsonUnserialize(is_array($decoded) ? $decoded : []);
        }

        return static::fromBinary($raw);
    }

    public function toStream(): StreamInterface
    {
        $payload = RequestAttributes::ResponseContentKind->read() === ContentKind::Json
            ? (string) json_encode($this)
            : $this->toBinary();

        return Stream::streamFor($payload);
    }
}
