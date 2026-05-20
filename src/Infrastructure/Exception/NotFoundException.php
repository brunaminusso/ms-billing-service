<?php

declare(strict_types=1);

namespace App\Infrastructure\Exception;

use Exception;

class NotFoundException extends Exception
{
    public function __construct(string $resource, int|string $id)
    {
        parent::__construct($resource . ' com ID ' . $id . ' nao encontrado', 404);
    }
}
