<?php

return [

    /*
    | Base da API da Life Academy (projeto la-app).
    | Ex.: https://api.lifeacademy.pro
    */
    'api_url' => rtrim((string) env('LA_API_URL', 'https://api.lifeacademy.pro'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Entrega da compra
    |--------------------------------------------------------------------------
    |
    | A API existente já sabe receber uma compra paga pela rota
    | POST /api/woocommerce/order. Ela espera o corpo de um webhook do
    | WooCommerce e valida a assinatura HMAC-SHA256 no header
    | X-Wc-Webhook-Signature.
    |
    | Mantemos esse contrato para não precisar mexer na API: o site novo
    | monta um payload no mesmo formato e assina com o mesmo segredo.
    |
    | 'endpoint' permite apontar para uma rota dedicada (ex.: /api/hotmart/order)
    | caso um dia a API ganhe uma. O formato do corpo não muda.
    */
    'purchase' => [
        'endpoint' => env('LA_PURCHASE_ENDPOINT', '/api/woocommerce/order'),

        /*
        | Segredo do HMAC. Precisa ser idêntico ao usado pela API em
        | WooCommerceController::webhook(). Hoje ele está hardcoded lá.
        */
        'webhook_secret' => env('LA_WEBHOOK_SECRET'),

        /*
        | Prefixo dos identificadores enviados à API. As colunas
        | woo_purchase_id, woo_purchase_number e order_key são UNIQUE na
        | tabela woocommerce_purchases; o prefixo garante que os pedidos do
        | site novo nunca colidam com os IDs numéricos herdados do WooCommerce.
        */
        'reference_prefix' => env('LA_REFERENCE_PREFIX', 'HM'),

        'timeout' => (int) env('LA_TIMEOUT', 30),

        /*
        | Quantas vezes reenviar antes de desistir. A entrega roda em fila,
        | então uma falha temporária da API não perde a venda.
        */
        'max_attempts' => (int) env('LA_MAX_ATTEMPTS', 8),
    ],

    /*
    | URL do painel do aluno, para onde mandamos o comprador depois da compra.
    */
    'app_url' => rtrim((string) env('LA_APP_URL', 'https://app.lifeacademy.pro'), '/'),

    'support_email' => env('LA_SUPPORT_EMAIL', 'suporte@lifeacademy.pro'),
];
