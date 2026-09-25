<?php

/*
|--------------------------------------------------------------------------
| Portfólio de produtos Life Academy
|--------------------------------------------------------------------------
|
| Cada produto pode ter uma ou mais variantes. A variante é o que de fato
| vai para o carrinho e, por consequência, para a API da Life Academy.
|
| `codenames` lista os bundles entregues pela variante. É exatamente a lista
| que a API espera: WooCommercePurchaseService::createCart() recebe um SKU
| com os codenames unidos por "-" e faz explode('-') para gerar um item de
| carrinho por codename. Mantemos o array aqui e montamos o SKU na hora de
| enviar, o que evita erros de digitação como o `bestus-big5-talent` do site
| atual (codename correto é `talents`, no plural).
|
| Preços em BRL. `list_price` é o "de" (preço cheio riscado) e `price` é o
| "por" (o que o cliente paga). O desconto percentual enviado à API é
| derivado desses dois valores.
|
*/

return [

    /*
    | Bundles conhecidos da API (tabela `bundles` da la-app).
    | `active` = false marca os bundles com deleted_at preenchido: a API
    | não os encontra em PurchaseService::registerPurchase(), então vender
    | um deles gera compra sem itens. Usado pelo comando catalog:check.
    */
    'bundles' => [
        'big5'       => ['name' => 'BIG 5 MAPA DE PERSONALIDADE',        'type' => 'map',     'active' => true],
        'grip'       => ['name' => 'CONEXÃO COM A PERSONALIDADE',        'type' => 'map',     'active' => true],
        'best'       => ['name' => 'O MELHOR DE MIM',                    'type' => 'program', 'active' => true],
        'done'       => ['name' => 'A ARTE DE FAZER ACONTECER',          'type' => 'program', 'active' => false],
        'life'       => ['name' => 'PARA ONDE VOU COM MINHA VIDA',       'type' => 'program', 'active' => false],
        'strength'   => ['name' => 'Pontos Fortes',                      'type' => 'program', 'active' => false],
        'where'      => ['name' => 'DE ONDE ESTOU PARA ONDE VOU',        'type' => 'program', 'active' => false],
        'talents'    => ['name' => 'MAPA DE TALENTOS & HABILIDADES',     'type' => 'map',     'active' => true],
        'bestus'     => ['name' => 'O MELHOR DE NÓS',                    'type' => 'program', 'active' => true],
        'big5single' => ['name' => 'BIG 5 MAPA DE PERSONALIDADE (SINGLE)', 'type' => 'program', 'active' => true],
    ],

    /*
    | Produtos exibidos na loja. A ordem aqui é a ordem de exibição.
    */
    'products' => [

        'big5' => [
            'slug'       => 'mapa-de-personalidade-big5',
            'image'      => 'assets/img/big5logo1.png',
            'cover'      => 'assets/img/life_001.png',
            'badge'      => 'featured',
            'featured'   => true,
            'variants'   => [
                [
                    'key'        => 'default',
                    'codenames'  => ['big5'],
                    'list_price' => 247.70,
                    'price'      => 197.00,
                ],
            ],
        ],

        'talents' => [
            'slug'       => 'mapa-de-talentos-e-habilidades',
            'image'      => 'assets/img/box2.png',
            'cover'      => 'assets/img/life_002.png',
            'badge'      => 'tool',
            'featured'   => true,
            'variants'   => [
                [
                    'key'        => 'default',
                    'codenames'  => ['talents'],
                    'list_price' => 197.70,
                    'price'      => 157.56,
                ],
            ],
        ],

        'best' => [
            'slug'       => 'programa-o-melhor-de-mim',
            'image'      => 'assets/img/box1.png',
            'cover'      => 'assets/img/life_004.png',
            'badge'      => 'program',
            'featured'   => true,
            'variants'   => [
                [
                    'key'        => 'with_maps',
                    'codenames'  => ['best', 'big5', 'talents'],
                    'list_price' => 997.70,
                    'price'      => 797.64,
                    'recommended' => true,
                ],
                [
                    'key'        => 'without_maps',
                    'codenames'  => ['best'],
                    'list_price' => 584.70,
                    'price'      => 467.16,
                ],
            ],
        ],

        'bestus' => [
            'slug'       => 'programa-o-melhor-de-nos',
            'image'      => 'assets/img/box3.png',
            'cover'      => 'assets/img/life_002.png',
            'badge'      => 'program',
            'featured'   => true,
            'variants'   => [
                [
                    'key'        => 'with_maps',
                    'codenames'  => ['bestus', 'big5', 'talents'],
                    'list_price' => 612.70,
                    'price'      => 489.60,
                    'recommended' => true,
                ],
                [
                    'key'        => 'duo_without_maps',
                    'codenames'  => ['best', 'bestus'],
                    'list_price' => 612.00,
                    'price'      => 489.60,
                ],
                [
                    'key'        => 'duo_with_maps',
                    'codenames'  => ['best', 'bestus', 'big5', 'talents'],
                    'list_price' => 1609.70,
                    'price'      => 1287.24,
                ],
            ],
        ],

        'big5single' => [
            'slug'       => 'big5-single-imersao',
            'image'      => 'assets/img/box5.png',
            'cover'      => 'assets/img/life_001.png',
            'badge'      => 'entry',
            'featured'   => false,
            'variants'   => [
                [
                    'key'        => 'default',
                    'codenames'  => ['big5single'],
                    'list_price' => 124.70,
                    'price'      => 99.70,
                ],
            ],
        ],

        /*
        | RM - Rain Maker usa o bundle `done`, que está soft-deleted na base
        | da la-app. Enquanto não for restaurado, a venda geraria uma compra
        | sem itens e sem token. Fica desabilitado por padrão; basta trocar
        | para true depois de restaurar o bundle.
        */
        'done' => [
            'slug'       => 'programa-rain-maker',
            'image'      => 'assets/img/box6.png',
            'cover'      => 'assets/img/life_004.png',
            'badge'      => 'program',
            'featured'   => false,
            'enabled'    => false,
            'variants'   => [
                [
                    'key'        => 'default',
                    'codenames'  => ['done'],
                    'list_price' => 112.40,
                    'price'      => 89.90,
                ],
            ],
        ],
    ],
];
