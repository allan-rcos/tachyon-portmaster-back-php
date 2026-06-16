<?php

use Allan\SwooleApiStack\Shared\Logging\ILogger;
use API\Controllers\Interno\ServerController;
use API\DTO\ProjectInfoDTO;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

describe('Test server controller', function() {
    /* @var ServerController|null $controller */
    $controller = null;
    beforeEach(function() use (&$controller) {
        $logger = Mockery::mock(ILogger::class)->shouldIgnoreMissing();
        $logger->shouldReceive('withChannel')->andReturn($logger);
        $controller = new ServerController($logger);
    });
    it('get project info', function() use (&$controller) {

        $request = Mockery::mock(Request::class);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')
            ->once()
            ->with(200);

        $response->shouldReceive('end')
            ->once()
            ->with(Mockery::on(function($arg) {
                $data = new ProjectInfoDTO(...json_decode($arg, true));

                expect($data->name)->toBe('allan/swoole-api-stack')
                    ->and($data->version)->toBe('0.0.1')
                    ->and($data->environment)->toBe('development')
                    ->and($data->runtime)->toStartWith('PHP ')
                    ->and($data->memory_usage_mb)->toBeGreaterThan(0);

                return true;
            }));

        $controller->getInfo($request, $response);

        expect(true)->toBeTrue();
    });
})->setGroups(['Controller']);
