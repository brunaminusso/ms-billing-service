<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use PHPUnit\Framework\TestCase;

class ActionPayloadTest extends TestCase
{
    public function testActionErrorExposesTypeDescriptionAndSerialization(): void
    {
        $error = new ActionError(ActionError::BAD_REQUEST, 'payload invalido');

        $this->assertSame(ActionError::BAD_REQUEST, $error->getType());
        $this->assertSame('payload invalido', $error->getDescription());

        $error->setType(ActionError::VALIDATION_ERROR)->setDescription('campo obrigatorio');

        $this->assertSame(
            ['type' => ActionError::VALIDATION_ERROR, 'description' => 'campo obrigatorio'],
            $error->jsonSerialize(),
        );
    }

    public function testActionPayloadSerializesDataBranch(): void
    {
        $payload = new ActionPayload(202, ['id' => 10]);

        $this->assertSame(202, $payload->getStatusCode());
        $this->assertSame(['id' => 10], $payload->getData());
        $this->assertNull($payload->getError());
        $this->assertSame(['statusCode' => 202, 'data' => ['id' => 10]], $payload->jsonSerialize());
    }

    public function testActionPayloadSerializesErrorBranch(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR, 'falha interna');
        $payload = new ActionPayload(500, null, $error);

        $this->assertSame($error, $payload->getError());
        $this->assertSame(
            ['statusCode' => 500, 'error' => $error],
            $payload->jsonSerialize(),
        );
    }
}
