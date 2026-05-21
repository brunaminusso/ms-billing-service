<?php

declare(strict_types=1);

namespace App\Domain\Service;

use MercadoPago\Resources\Preference;

interface MercadoPagoPreferenceClientInterface
{
    public function createPreference(array $request): Preference;
}
