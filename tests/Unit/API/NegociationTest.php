<?php

declare(strict_types=1);

use API\Http\ContentKind;
use API\Http\MediaType;
use API\Negociation\DTO\Auth\LoginXRequest;
use API\Negociation\DTO\Auth\LoginXRequestFactory;
use API\Negociation\DTO\Auth\LoginXResponse;
use API\Negociation\DTO\Auth\LoginXResponseFactory;
use API\Negociation\DTO\Auth\UserX;
use API\Negociation\DTO\Common\ProblemDetailsX;
use API\Negociation\DTO\Common\ProblemDetailsXFactory;
use API\Negociation\Interno\AcceptsStrategyContext;
use API\Negociation\Interno\ContentTypeStrategyContext;
use API\Negociation\Interno\FlatbufferAcceptsStrategy;
use API\Negociation\Interno\FlatbufferContentTypeStrategy;
use API\Negociation\Interno\JsonAcceptsStrategy;
use API\Negociation\Interno\JsonContentTypeStrategy;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use OpenSwoole\Coroutine;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Runs `$work` inside a coroutine, which is where the strategy contexts keep
 * the request's choice. Outside one they only ever see their fallback.
 */
function inCoroutine(Closure $work): void
{
    Coroutine::run($work);
}

/**
 * The bytes of a rendered message, as the accepts strategy would put them on
 * the wire.
 */
function rendered(object $strategy, IResponseAbstractFactory $factory): string
{
    $stream = $strategy->toStream($factory);
    expect($stream->isSuccess())->toBeTrue();

    return (string) $stream->getValue();
}

describe('Negotiation strategies', function () {
    it('decodes a JSON body through the factory the controller supplies', function () {
        $body = Stream::streamFor('{"email":"a@example.com","password":"hunter2"}');

        $decoded = (new JsonContentTypeStrategy())->execute($body, new LoginXRequestFactory());
        $message = $decoded->getValue();

        expect($decoded->isSuccess())->toBeTrue()
            ->and($message)->toBeInstanceOf(LoginXRequest::class)
            ->and($message->email)->toBe('a@example.com')
            ->and($message->password)->toBe('hunter2');
    });

    it('decodes a FlatBuffer body through the same factory', function () {
        // The only round trip in the suite that goes out and back in: the
        // response side writes the bytes the request side then reads. The
        // builder belongs to the strategy at both ends.
        $bytes = rendered(
            new FlatbufferAcceptsStrategy(),
            new LoginXResponseFactory(new LoginXResponse(token: 'T', tokenType: 'cookie')),
        );

        $decoded = (new FlatbufferContentTypeStrategy())->execute(
            Stream::streamFor($bytes),
            new LoginXRequestFactory(),
        );

        // LoginRequest and LoginResponse share the first two string fields, so
        // the buffer parses cleanly — what matters here is that the binary door
        // was taken at all.
        expect($decoded->getValue())->toBeInstanceOf(LoginXRequest::class);
    });

    it('fails on an empty body, in either format', function () {
        // Only an action that reads a body calls a strategy at all, so a
        // request without one is a request missing its message. Nothing is
        // invented to stand in for it: the factory is never reached.
        $binary = (new FlatbufferContentTypeStrategy())->execute(Stream::streamFor(''), new LoginXRequestFactory());
        $json   = (new JsonContentTypeStrategy())->execute(Stream::streamFor('  '), new LoginXRequestFactory());

        expect($binary->isSuccess())->toBeFalse()
            ->and(Leaf::getError($binary->getErrorId())?->code)->toBe(404)
            ->and($json->isSuccess())->toBeFalse()
            ->and(Leaf::getError($json->getErrorId())?->code)->toBe(404);
    });

    it('always carries the message on a success', function () {
        $decoded = (new JsonContentTypeStrategy())->execute(
            Stream::streamFor('{"email":"a@example.com"}'),
            new LoginXRequestFactory(),
        );

        expect($decoded->isEmpty())->toBeFalse()
            ->and($decoded->getValue())->toBeInstanceOf(LoginXRequest::class);
    });

    it('fails on a body that was sent and cannot be read', function () {
        // Bytes that are not what the `Content-Type` promised. The controller
        // decides what that becomes; the registered status says 404.
        $junk = (new JsonContentTypeStrategy())->execute(Stream::streamFor('not json'), new LoginXRequestFactory());

        expect($junk->isSuccess())->toBeFalse()
            ->and(Leaf::getError($junk->getErrorId())?->code)->toBe(404);
    });

    it('renders the same message in both formats, keyed by the schema names', function () {
        $factory = new LoginXResponseFactory(new LoginXResponse(
            token: 'T',
            tokenType: 'cookie',
            user: new UserX(id: 'U1', name: 'Allan', email: 'a@example.com'),
        ));

        $json = json_decode(rendered(new JsonAcceptsStrategy(), $factory), true);
        $fbs  = rendered(new FlatbufferAcceptsStrategy(), $factory);

        // snake_case, from the .fbs — not the DTO's camelCase properties.
        expect($json)->toHaveKey('token_type')
            ->and($json['token_type'])->toBe('cookie')
            ->and($json['user']['email'])->toBe('a@example.com')
            ->and($fbs)->not->toBe('')
            ->and($fbs)->not->toBe((string) json_encode($json));
    });

    it('hands a factory failure straight back, in either format', function () {
        // Nothing in the application fails this way today; what is being
        // asserted is that a strategy never invents a body for a message the
        // factory refused to build.
        $broken = new class implements IResponseAbstractFactory
        {
            public function createFlatbuffer(FlatbufferBuilder $builder): Result
            {
                return Result::failure(Leaf::newError(new LeafContext('nope', null, 502)));
            }

            public function createJson(): Result
            {
                return Result::failure(Leaf::newError(new LeafContext('nope', null, 502)));
            }
        };

        $json = (new JsonAcceptsStrategy())->toStream($broken);
        $fbs  = (new FlatbufferAcceptsStrategy())->toStream($broken);

        expect($json->isSuccess())->toBeFalse()
            ->and(Leaf::getError($json->getErrorId())?->code)->toBe(502)
            ->and($fbs->isSuccess())->toBeFalse()
            ->and(Leaf::getError($fbs->getErrorId())?->code)->toBe(502);
    });

    it('gives an error the problem media type only on the JSON branch', function () {
        // The strategies cannot be asked what they are; the kind the middleware
        // resolved names the answer, and the status is what makes it a problem
        // document.
        expect(ContentKind::Json->mediaType())->toBe(MediaType::Json)
            ->and(ContentKind::Json->mediaType(404))->toBe(MediaType::ProblemJson)
            ->and(ContentKind::Fbs->mediaType())->toBe(MediaType::Fbs)
            ->and(ContentKind::Fbs->mediaType(404))->toBe(MediaType::Fbs);
    });

    it('renders a problem document in both formats, like any other message', function () {
        $factory = new ProblemDetailsXFactory(new ProblemDetailsX(
            type: 'about:blank',
            title: 'Not Found',
            status: 404,
            detail: 'No such product.',
        ));

        $json = json_decode(rendered(new JsonAcceptsStrategy(), $factory), true);
        $fbs  = rendered(new FlatbufferAcceptsStrategy(), $factory);

        expect($json['status'])->toBe(404)
            ->and($json['detail'])->toBe('No such product.')
            ->and($fbs)->not->toBe('');
    });
})->group('API', 'Negociation');

describe('Negotiation contexts', function () {
    it('falls back to the strategy it was built with when nothing was negotiated', function () {
        // The fallbacks the provider supplies mirror ContentKind's own defaults:
        // a body with no `Content-Type` is binary, and an answer with no
        // `Accept` is JSON — the format any client can read, which is what the
        // recoverer needs when it catches a failure raised before negotiation
        // ran.
        inCoroutine(function () {
            $accepts = new AcceptsStrategyContext(new JsonAcceptsStrategy());

            $body = rendered($accepts, new LoginXResponseFactory(new LoginXResponse(token: 'T')));

            expect(json_decode($body, true))->toBeArray();
        });
    });

    it('uses the strategy the middleware recorded for the request', function () {
        inCoroutine(function () {
            $contentType = new ContentTypeStrategyContext(new FlatbufferContentTypeStrategy());
            $contentType->setStrategy(new JsonContentTypeStrategy());

            $decoded = $contentType->execute(
                Stream::streamFor('{"email":"a@example.com"}'),
                new LoginXRequestFactory(),
            );

            $accepts = new AcceptsStrategyContext(new JsonAcceptsStrategy());
            $accepts->setStrategy(new FlatbufferAcceptsStrategy());

            // Nothing can ask the context which strategy it is holding — what
            // it recorded shows in the bytes it produces.
            $body = rendered($accepts, new LoginXResponseFactory(new LoginXResponse(token: 'T')));

            expect($decoded->getValue()->email)->toBe('a@example.com')
                ->and(json_decode($body, true))->toBeNull();
        });
    });

    it('does not leak one request\'s choice into another', function () {
        // The whole reason the choice lives in the coroutine context rather than
        // on the context object: two requests served concurrently by one worker
        // share that object.
        $accepts = new AcceptsStrategyContext(new JsonAcceptsStrategy());
        $message = new LoginXResponseFactory(new LoginXResponse(token: 'T'));
        $seen    = [];

        Coroutine::run(function () use ($accepts, $message, &$seen) {
            Coroutine::create(function () use ($accepts, $message, &$seen) {
                $accepts->setStrategy(new FlatbufferAcceptsStrategy());
                $seen['inner'] = rendered($accepts, $message);
            });

            // This coroutine never set a strategy, so it must still see the
            // default, not the sibling's.
            $seen['outer'] = rendered($accepts, $message);
        });

        // Binary in one, JSON in the other — the same object, two coroutines.
        expect(json_decode($seen['inner'], true))->toBeNull()
            ->and(json_decode($seen['outer'], true))->toBeArray();
    });
})->group('API', 'Negociation');
