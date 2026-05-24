# Billing Service

[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=ms-billing-service&metric=coverage)](https://sonarcloud.io/dashboard?id=ms-billing-service)

Serviço responsável por orçamentos e pagamentos das ordens de serviço.

## Responsabilidades

- Geração e envio de orçamentos
- Registro e verificação de pagamentos
- Webhook do Mercado Pago

## Integração

- Mercado Pago (webhooks e cobrancas)

Este serviço usa o SDK oficial `mercadopago/dx-php`.

## Integracao Mercado Pago local

### Passo a passo

1. Crie uma conta de teste no Mercado Pago Developers e gere um `access_token` de teste.
2. Copie `.env.example` para `.env` e preencha:
	- `MP_PUBLIC_KEY` com a chave publica do checkout, se voce for testar o fluxo no cliente.
	- `MP_ACCESS_TOKEN` com o token de teste.
	- `MP_BASE_URI` com `https://api.mercadopago.com`.
	- `MP_RUNTIME_ENVIRONMENT` com `server` ou `local`.
3. Suba o Billing Service com `composer start` ou Docker.
4. Crie um orcamento em `POST /api/v1/quotations`.
5. Crie o pagamento em `POST /api/v1/payments`.
6. Salve o `preference_id` e o `init_point` retornados.
7. Abra o `init_point`, conclua um pagamento de teste e acompanhe o status em `GET /api/v1/payments/{id}`.
8. Para processar o webhook, envie o `data.id` do pagamento retornado pelo Mercado Pago em `POST /api/v1/payments/webhook/mercadopago`.

### Observacoes

- O comportamento de aprovacao/recusa depende da conta de teste e do meio de pagamento escolhido.
- Se quiser validar o webhook de ponta a ponta no ambiente local, use um tunnel como `ngrok` para expor o endpoint.

### Exemplos com `curl`

Defina um alias para facilitar os testes locais:

```bash
export BILLING_BASE_URL="http://localhost:8080/api/v1"
```

Ou execute o script pronto:

```bash
bash scripts/mercado-pago-sandbox.sh
```

1. Criar um orcamento e guardar o `quotation_id`:

```bash
QUOTATION_ID=$(curl -s -X POST "$BILLING_BASE_URL/quotations" \
	-H 'Content-Type: application/json' \
	-d '{
		"service_order_id": 1,
		"total_in_cents": 21500,
		"currency": "BRL"
	}' | jq -r '.id')

echo "$QUOTATION_ID"
```

2. Consultar o orcamento criado:

```bash
curl "$BILLING_BASE_URL/quotations/$QUOTATION_ID"
```

3. Criar a `preference` e guardar o `payment_id`, o `preference_id` e o `init_point`:

```bash
PAYMENT_RESPONSE=$(curl -s -X POST "$BILLING_BASE_URL/payments" \
	-H 'Content-Type: application/json' \
	-d '{
		"quotation_id": '"$QUOTATION_ID"',
		"amount_in_cents": 21500,
		"payer_email": "teste@example.com",
		"currency": "BRL"
	}')

echo "$PAYMENT_RESPONSE"

PAYMENT_ID=$(echo "$PAYMENT_RESPONSE" | jq -r '.id')
PREFERENCE_ID=$(echo "$PAYMENT_RESPONSE" | jq -r '.preference_id')
INIT_POINT=$(echo "$PAYMENT_RESPONSE" | jq -r '.init_point')
```

4. Abrir o checkout e concluir o pagamento de teste:

```bash
echo "$INIT_POINT"
```

5. Consultar o pagamento local criado para a fatura:

```bash
curl "$BILLING_BASE_URL/payments/$PAYMENT_ID"
```

6. Simular o webhook do Mercado Pago localmente com o `data.id` do pagamento real:

```bash
curl -X POST "$BILLING_BASE_URL/payments/webhook/mercadopago" \
	-H 'Content-Type: application/json' \
	-d '{
		"type": "payment",
		"data": {
			"id": "<PAYMENT_ID_DO_MERCADO_PAGO>"
		}
	}'
```

Use no `data.id` o ID do pagamento gerado pelo Checkout Pro, não o `preference_id`.

Se voce nao tiver `jq`, substitua os comandos de captura pelos valores impressos na resposta JSON.

## Banco de dados

- MySQL

## Endpoints

- POST `/api/v1/quotations`
- GET `/api/v1/quotations/{id}`
- POST `/api/v1/payments`
- GET `/api/v1/payments/{id}`
- POST `/api/v1/payments/webhook/mercadopago`

## Migrations

```bash
cp .env.example .env
composer migrate
```

## OpenAPI

- Documento: `public/openapi.json`

## Evidencias de cobertura

- Workflow: https://github.com/brunaminusso/ms-billing-service/actions/workflows/tests.yml
- Artefato de cobertura publicado no CI: `build/coverage.xml`
- Verificacao local opcional: `composer coverage:check`

## CI / Sonar

- Painel Sonar: https://sonarcloud.io/dashboard?id=ms-billing-service
- O workflow de CI executa build, testes, análise estática e um scan SonarQube opcional.
- Para habilitar o Sonar no CI, defina os secrets do repositório: `SONAR_HOST_URL`, `MS_SONAR_TOKEN` e, se necessário, `SONAR_ORGANIZATION`.
- O workflow só executa o step do Sonar quando `MS_SONAR_TOKEN` estiver presente.
- O gate principal de cobertura fica centralizado no Sonar; `composer coverage:check` pode ser usado localmente como validacao adicional.

## Teste BDD

- Webhook Mercado Pago: [tests/Presentation/BillingControllerBddTest.php](tests/Presentation/BillingControllerBddTest.php)

## Saga Pattern (orquestrada)

Neste desenho, o Billing Service participa da saga coordenada pelo OS Service:

1) Recebe a solicitacao para gerar o orcamento da ordem de servico.
2) Registra o pagamento e confirma a aprovacao via evento `billing.payment.approved`.
3) Em caso de falha operacional, devolve erro ao orquestrador para acionar compensacao.

Compensacoes sob responsabilidade do dominio de Billing:
- pagamento recusado ou expirado;
- reembolso solicitado pelo orquestrador quando a execucao nao puder iniciar;
- registro do estado final para auditoria do fluxo financeiro.

## Eventos (RabbitMQ)

- Publica `billing.payment.approved` no exchange `billing.events`

## Como rodar (dev)

```bash
composer start
```

Ou com Docker:

```bash
docker-compose up -d
```
