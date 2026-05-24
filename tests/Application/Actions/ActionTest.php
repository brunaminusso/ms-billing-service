<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\Action;
use App\Application\Actions\ActionPayload;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Psr7\Response as SlimResponse;
use Tests\TestCase;

class ActionTest extends TestCase
{
    public function testActionSetsHttpCodeInRespond()
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $logger = $container->get(LoggerInterface::class);

        $testAction = new class ($logger) extends Action {
            public function __construct(
                LoggerInterface $loggerInterface
            ) {
                parent::__construct($loggerInterface);
            }

            public function action(): Response
            {
                return $this->respond(
                    new ActionPayload(
                        202,
                        [
                            'willBeDoneAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM)
                        ]
                    )
                );
            }
        };

        $app->get('/test-action-response-code', $testAction);
        $request = $this->createRequest('GET', '/test-action-response-code');
        $response = $app->handle($request);

        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testActionSetsHttpCodeRespondData()
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $logger = $container->get(LoggerInterface::class);

        $testAction = new class ($logger) extends Action {
            public function __construct(
                LoggerInterface $loggerInterface
            ) {
                parent::__construct($loggerInterface);
            }

            public function action(): Response
            {
                return $this->respondWithData(
                    [
                        'willBeDoneAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM)
                    ],
                    202
                );
            }
        };

        $app->get('/test-action-response-code', $testAction);
        $request = $this->createRequest('GET', '/test-action-response-code');
        $response = $app->handle($request);

        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testActionReturnsParsedBodyFromRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $action = new TestableAction($logger, static fn (): Response => new SlimResponse());
        $request = $this->createRequest('POST', '/test-action');
        $request = $request->withParsedBody(['foo' => 'bar']);

        $action($request, new SlimResponse(), []);

        $this->assertSame(['foo' => 'bar'], $action->fetchFormData());
    }

    public function testActionResolvesArgumentWhenPresent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $action = new TestableAction($logger, static fn (): Response => new SlimResponse());

        $action($this->createRequest('GET', '/test-action'), new SlimResponse(), ['id' => 15]);

        $this->assertSame(15, $action->fetchArg('id'));
    }

    public function testActionThrowsBadRequestWhenArgumentIsMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $action = new TestableAction($logger, static fn (): Response => new SlimResponse());

        $action($this->createRequest('GET', '/test-action'), new SlimResponse(), []);

        $this->expectException(HttpBadRequestException::class);
        $action->fetchArg('id');
    }
}
