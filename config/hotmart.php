<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hottok
    |--------------------------------------------------------------------------
    |
    | Token único da conta, enviado pela Hotmart no header X-HOTMART-HOTTOK de
    | cada webhook. Encontrado em Ferramentas > Webhook no painel.
    |
    | É a única coisa que separa uma venda real de um POST forjado, já que o
    | webhook cria usuário e libera acesso na la-app.
    |
    */
    'hottok' => env('HOTMART_HOTTOK'),

    /*
    |--------------------------------------------------------------------------
    | Credenciais da API
    |--------------------------------------------------------------------------
    |
    | Criadas em Hotmart Developers > suas credenciais. Usadas para consultar
    | preços das ofertas (hotmart:sync) e para reconferir uma venda pelo código
    | da transação.
    |
    | O webhook funciona sem elas; a sincronização de preço, não.
    |
    */
    'client_id'     => env('HOTMART_CLIENT_ID'),
    'client_secret' => env('HOTMART_CLIENT_SECRET'),

    /*
    | Token Basic pré-codificado que o painel exibe junto das credenciais.
    | Se ausente, é derivado de client_id:client_secret.
    */
    'basic' => env('HOTMART_BASIC'),

    'endpoints' => [
        'token'    => 'https://api-sec-vlc.hotmart.com/security/oauth/token',
        'payments' => 'https://developers.hotmart.com/payments/api/v1',
        'products' => 'https://developers.hotmart.com/products/api/v1',
    ],

    /*
    | Base do checkout hospedado. A URL final de uma oferta fica
    | https://pay.hotmart.com/{product_code}?off={offer_code}
    */
    'checkout_url' => env('HOTMART_CHECKOUT_URL', 'https://pay.hotmart.com'),

    /*
    |--------------------------------------------------------------------------
    | Eventos tratados
    |--------------------------------------------------------------------------
    |
    | PURCHASE_APPROVED  pagamento aprovado — é o que libera o acesso
    | PURCHASE_COMPLETE  prazo de garantia encerrado
    | PURCHASE_REFUNDED  reembolso
    | PURCHASE_CHARGEBACK contestação no cartão
    | PURCHASE_CANCELED  compra cancelada antes de aprovar
    |
    | PURCHASE_COMPLETE não reentrega nada: quem libera é o APPROVED. Está na
    | lista só para o log e para marcar a saída da janela de garantia.
    |
    */
    'events' => [
        'grant'  => ['PURCHASE_APPROVED'],
        'revoke' => ['PURCHASE_REFUNDED', 'PURCHASE_CHARGEBACK', 'PURCHASE_CANCELED', 'PURCHASE_EXPIRED'],
        'note'   => ['PURCHASE_COMPLETE', 'PURCHASE_BILLET_PRINTED', 'PURCHASE_PROTEST', 'PURCHASE_DELAYED'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronização de preço
    |--------------------------------------------------------------------------
    |
    | O preço mostrado no site precisa ser o mesmo que a Hotmart cobra. O
    | comando hotmart:sync lê as ofertas pela API e reescreve
    | config/catalog.php. Rode no deploy e num cron diário.
    |
    */
    'sync' => [
        'enabled' => filter_var(env('HOTMART_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        /*
        | Diferença percentual a partir da qual o comando avisa em vez de
        | aplicar calado. Uma queda de 90% costuma ser erro de cadastro.
        */
        'alert_threshold' => (float) env('HOTMART_SYNC_ALERT_THRESHOLD', 25.0),
    ],

    'timeout' => (int) env('HOTMART_TIMEOUT', 20),
];
