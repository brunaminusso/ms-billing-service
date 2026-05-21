<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Service\MercadoPagoPaymentClientInterface;
use App\Domain\Service\MercadoPagoPreferenceClientInterface;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Preference;
use MercadoPago\Resources\Payment;
use RuntimeException;

final class MercadoPagoPaymentClientAdapter implements
    MercadoPagoPaymentClientInterface,
    MercadoPagoPreferenceClientInterface
{
    private PaymentClient $client;
    private PreferenceClient $preferenceClient;

    public function __construct()
    {
        $accessToken = getenv('MP_ACCESS_TOKEN');

        if (!$accessToken) {
            throw new RuntimeException('MP_ACCESS_TOKEN not set');
        }

        MercadoPagoConfig::$BASE_URL = getenv('MP_BASE_URI') ?: MercadoPagoConfig::$BASE_URL;
        MercadoPagoConfig::setAccessToken($accessToken);
        MercadoPagoConfig::setRuntimeEnviroment(getenv('MP_RUNTIME_ENVIRONMENT') ?: MercadoPagoConfig::SERVER);

        $this->client = new PaymentClient();
        $this->preferenceClient = new PreferenceClient();
    }

    public function create(array $request): Payment
    {
        return $this->client->create($request);
    }

    public function get(int $id): Payment
    {
        return $this->client->get($id);
    }

    public function createPreference(array $request): Preference
    {
        return $this->preferenceClient->create($request);
    }
}
