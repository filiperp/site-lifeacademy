<?php

return [

    /*
    | sandbox | production
    */
    'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'),

    'base_url' => env('ASAAS_ENVIRONMENT', 'sandbox') === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://api-sandbox.asaas.com/v3',

    /*
    | Chave de API da conta Asaas. Enviada no header `access_token`.
    | Sandbox e produção têm chaves distintas.
    */
    'api_key' => env('ASAAS_API_KEY'),

    /*
    | Token que o Asaas envia no header `asaas-access-token` de cada webhook.
    | É definido por você ao cadastrar o webhook no painel do Asaas.
    */
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),

    /*
    | Meios de pagamento oferecidos no Checkout hospedado.
    | Valores aceitos: CREDIT_CARD, PIX, BOLETO.
    */
    'billing_types' => array_filter(array_map('trim', explode(',', (string) env('ASAAS_BILLING_TYPES', 'CREDIT_CARD,PIX,BOLETO')))),

    /*
    | Parcelamento no cartão. O Asaas aceita de 1 a 21 parcelas.
    */
    'installments' => [
        'enabled' => (bool) env('ASAAS_INSTALLMENTS_ENABLED', true),
        'max'     => (int) env('ASAAS_MAX_INSTALLMENTS', 12),

        /*
        | Valor mínimo de cada parcela. Usado apenas para exibir a simulação
        | na vitrine e no carrinho — o parcelamento efetivo é escolhido pelo
        | cliente na página do Asaas, limitado por `max`.
        */
        'min_installment_value' => (float) env('ASAAS_MIN_INSTALLMENT_VALUE', 25.00),

        /*
        | Quem paga os juros do parcelamento.
        | 'merchant' = parcelado sem juros para o cliente (loja absorve).
        | 'customer' = juros repassados; a simulação exibe "com juros".
        */
        'interest_bearer' => env('ASAAS_INTEREST_BEARER', 'merchant'),
    ],

    /*
    | Tempo de vida do link de checkout, em minutos (10 a 1440).
    */
    'minutes_to_expire' => (int) env('ASAAS_MINUTES_TO_EXPIRE', 60),

    'timeout' => (int) env('ASAAS_TIMEOUT', 20),
];
