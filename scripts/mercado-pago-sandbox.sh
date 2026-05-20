#!/usr/bin/env bash

set -euo pipefail

BASE_URL="${BILLING_BASE_URL:-http://localhost:8080/api/v1}"
SERVICE_ORDER_ID="${SERVICE_ORDER_ID:-1}"
TOTAL_IN_CENTS="${TOTAL_IN_CENTS:-21500}"
CURRENCY="${CURRENCY:-BRL}"
PAYER_EMAIL="${PAYER_EMAIL:-teste@example.com}"
WEBHOOK_PAYMENT_ID="${WEBHOOK_PAYMENT_ID:-}"

json_get() {
	php -r '$data = json_decode(stream_get_contents(STDIN), true); echo $data[$argv[1]] ?? "";' "$1"
}

echo "Criando orcamento em ${BASE_URL}/quotations..."
quotation_response="$(curl -sS -X POST "${BASE_URL}/quotations" \
	-H 'Content-Type: application/json' \
	-d "$(cat <<EOF
{
	"service_order_id": ${SERVICE_ORDER_ID},
	"total_in_cents": ${TOTAL_IN_CENTS},
	"currency": "${CURRENCY}"
}
EOF
)" )"

quotation_id="$(printf '%s' "${quotation_response}" | json_get id)"

if [[ -z "${quotation_id}" ]]; then
	echo "Falha ao criar orcamento:"
	echo "${quotation_response}"
	exit 1
fi

echo "Quotation ID: ${quotation_id}"

echo "Consultando orcamento..."
curl -sS "${BASE_URL}/quotations/${quotation_id}" | php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'

echo ""
echo "Criando pagamento no Mercado Pago..."
payment_response="$(curl -sS -X POST "${BASE_URL}/payments" \
	-H 'Content-Type: application/json' \
	-d "$(cat <<EOF
{
	"quotation_id": ${quotation_id},
	"amount_in_cents": ${TOTAL_IN_CENTS},
	"payer_email": "${PAYER_EMAIL}",
	"currency": "${CURRENCY}"
}
EOF
)" )"

payment_id="$(printf '%s' "${payment_response}" | json_get id)"
preference_id="$(printf '%s' "${payment_response}" | json_get preference_id)"
init_point="$(printf '%s' "${payment_response}" | json_get init_point)"

if [[ -z "${payment_id}" || -z "${preference_id}" || -z "${init_point}" ]]; then
	echo "Falha ao criar pagamento:"
	echo "${payment_response}"
	exit 1
fi

echo "Payment ID: ${payment_id}"
echo "Preference ID: ${preference_id}"
echo "Init Point: ${init_point}"

echo ""
echo "Consultando pagamento..."
curl -sS "${BASE_URL}/payments/${payment_id}" | php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'

if [[ -n "${WEBHOOK_PAYMENT_ID}" ]]; then
	echo ""
	echo "Simulando webhook do Mercado Pago..."
	webhook_response="$(curl -sS -X POST "${BASE_URL}/payments/webhook/mercadopago" \
		-H 'Content-Type: application/json' \
		-d "$(cat <<EOF
{
	"type": "payment",
	"data": {
		"id": "${WEBHOOK_PAYMENT_ID}"
	}
}
EOF
)" )"

	echo "${webhook_response}" | php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'
fi
