<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class TestableAction extends Action
{
    /** @var callable */
    private $callback;

    public function __construct(LoggerInterface $logger, callable $callback)
    {
        parent::__construct($logger);
        $this->callback = $callback;
    }

    protected function action(): Response
    {
        $callback = $this->callback;

        return $callback();
    }

    public function fetchFormData()
    {
        return $this->getFormData();
    }

    public function fetchArg(string $name)
    {
        return $this->resolveArg($name);
    }
}
