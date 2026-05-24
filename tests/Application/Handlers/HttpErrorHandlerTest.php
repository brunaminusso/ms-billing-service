<?php

declare(strict_types=1);

namespace Tests\Application\Handlers;

use App\Application\Actions\ActionError;
use App\Application\Handlers\HttpErrorHandler;
use App\Infrastructure\Exception\NotFoundException;
use App\Infrastructure\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class HttpErrorHandlerTest extends TestCase
{
    /**
     * @dataProvider httpExceptionProvider
     */
    public function testMapsHttpExceptionsToExpectedErrorPayload(
        \Throwable $exception,
        int $expectedStatus,
        string $expectedType,
        string $expectedDescription
    ): void {
        $handler = $this->createHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/billing');

        $response = $handler($request, $exception, false, false, false);
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedType, $payload['error']['type']);
        $this->assertSame($expectedDescription, $payload['error']['description']);
    }

    public function testMapsDomainNotFoundException(): void
    {
        $handler = $this->createHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/billing');

        $response = $handler(
            $request,
            new NotFoundException('Pagamento', 10),
            false,
            false,
            false,
        );

        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(ActionError::RESOURCE_NOT_FOUND, $payload['error']['type']);
        $this->assertSame('Pagamento com ID 10 nao encontrado', $payload['error']['description']);
    }

    public function testMapsValidationException(): void
    {
        $handler = $this->createHandler();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/billing');

        $response = $handler(
            $request,
            new ValidationException('payload invalido'),
            false,
            false,
            false,
        );

        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(ActionError::BAD_REQUEST, $payload['error']['type']);
        $this->assertSame('payload invalido', $payload['error']['description']);
    }

    public function testKeepsGenericMessageWhenErrorDetailsAreDisabled(): void
    {
        $handler = $this->createHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/billing');

        $response = $handler($request, new \RuntimeException('falha interna'), false, false, false);
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(ActionError::SERVER_ERROR, $payload['error']['type']);
        $this->assertSame(
            'An internal error has occurred while processing your request.',
            $payload['error']['description']
        );
    }

    public function testExposesGenericThrowableMessageWhenErrorDetailsAreEnabled(): void
    {
        $handler = $this->createHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/billing');

        $response = $handler($request, new \RuntimeException('falha interna'), true, false, false);
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(ActionError::SERVER_ERROR, $payload['error']['type']);
        $this->assertSame('falha interna', $payload['error']['description']);
    }

    public function httpExceptionProvider(): array
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/billing');

        return [
            'not found' => [
                new HttpNotFoundException($request, 'nao encontrado'),
                404,
                ActionError::RESOURCE_NOT_FOUND,
                'nao encontrado',
            ],
            'method not allowed' => [
                new HttpMethodNotAllowedException($request, 'rota invalida'),
                405,
                ActionError::NOT_ALLOWED,
                'rota invalida',
            ],
            'unauthorized' => [
                new HttpUnauthorizedException($request, 'sem token'),
                401,
                ActionError::UNAUTHENTICATED,
                'sem token',
            ],
            'forbidden' => [
                new HttpForbiddenException($request, 'acesso negado'),
                403,
                ActionError::INSUFFICIENT_PRIVILEGES,
                'acesso negado',
            ],
            'bad request' => [
                new HttpBadRequestException($request, 'payload invalido'),
                400,
                ActionError::BAD_REQUEST,
                'payload invalido',
            ],
            'not implemented' => [
                new HttpNotImplementedException($request, 'nao implementado'),
                501,
                ActionError::NOT_IMPLEMENTED,
                'nao implementado',
            ],
        ];
    }

    private function createHandler(): HttpErrorHandler
    {
        $callableResolver = $this->createMock(CallableResolverInterface::class);

        return new HttpErrorHandler($callableResolver, new ResponseFactory());
    }
}
