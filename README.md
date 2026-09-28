# Site Life Academy

Recriação do site lifeacademy.pro em Laravel 13, substituindo o WordPress +
WooCommerce + Elementor por uma loja própria com checkout parcelado no Asaas.

A entrega da compra continua sendo feita pela API existente (`la-app`), **sem
nenhuma alteração nela**.

---

## Como rodar

Requer PHP 8.3+, Composer e Node 20+.

```sh
# vendor/ e node_modules/ não vão para o git; num checkout novo estes dois
# primeiros passos são obrigatórios — sem eles o artisan nem carrega.
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm run build
php artisan serve
```

Testes:

```sh
php artisan test
php artisan catalog:check          # catálogo contra os bundles conhecidos
php artisan catalog:check --remote # confere também contra GET /api/bundle
```

---

## Credenciais necessárias

Nada disso está no repositório — preencha no `.env`.

| Variável | Onde obter | Sem ela |
|---|---|---|
| `ASAAS_API_KEY` | Painel Asaas → Integrações → Chave de API (sandbox e produção são chaves diferentes) | O checkout não é criado; o cliente vê mensagem de erro |
| `ASAAS_WEBHOOK_TOKEN` | Você escolhe ao cadastrar o webhook no painel do Asaas | O webhook é recusado em produção e nenhuma compra é confirmada |
| `LA_WEBHOOK_SECRET` | O mesmo segredo usado hoje em `WooCommerceController::webhook()` da `la-app` | A assinatura não bate (hoje isso não bloqueia a API, mas vai bloquear se o HMAC passar a ser validado) |
| `LA_API_URL` | URL pública da `la-app` | Sem destino para a compra |

O site **não guarda dado de cartão**. O pagamento acontece na página hospedada
do Asaas, o que mantém o projeto fora do escopo de PCI-DSS.

### Webhook a cadastrar no painel do Asaas

```
URL:    https://SEU-DOMINIO/webhooks/asaas
Token:  o mesmo valor de ASAAS_WEBHOOK_TOKEN
Versão: v3
Eventos: PAYMENT_CONFIRMED, PAYMENT_RECEIVED, PAYMENT_REFUNDED,
         PAYMENT_DELETED, CHECKOUT_PAID, CHECKOUT_CANCELED, CHECKOUT_EXPIRED
```

---

## Fluxo de compra

```
Carrinho (sessão)
   │
   ▼
POST /checkout ──► cria Order (status pending) ──► POST /v3/checkouts no Asaas
   │                                                        │
   │                                            devolve id + link do checkout
   │                                                        │
   └──────────── redirect para a página do Asaas ◄──────────┘
                              │
              cliente paga (cartão parcelado, Pix ou boleto)
                              │
       ┌──────────────────────┴───────────────────────┐
       ▼                                              ▼
POST /webhooks/asaas                        GET /checkout/{uuid}/retorno
(confirma a compra)                         (só informativo; consulta o status)
       │
       ▼
Order.status = paid
       │
       ▼
DeliverPurchaseToLifeAcademy (fila, com retentativa)
       │
       ▼
POST {LA_API_URL}/api/woocommerce/order   ← a API existente, sem alteração
       │
       ▼
la-app: cria usuário, registra a compra, gera tokens e envia o e-mail
```

O que confirma a venda é o **webhook**, nunca o retorno do navegador — aquela
URL pode ser aberta por qualquer pessoa.

---

## Por que a API não precisou mudar

`POST /api/woocommerce/order` já faz tudo o que uma compra nova exige:
localiza ou cria o usuário, registra a compra, gera os tokens de acesso e
dispara o e-mail de boas-vindas. Ela só espera o corpo no formato do webhook
do WooCommerce.

O site monta exatamente esse corpo (`app/Services/LifeAcademy/PurchaseGateway.php`):

| Campo enviado | O que a API faz com ele |
|---|---|
| `id`, `number`, `order_key` | Colunas UNIQUE de `woocommerce_purchases`. Prefixamos com `AS-`/`la_` para nunca colidir com os IDs numéricos herdados do WooCommerce |
| `status: completed` | `webhook()` só processa `COMPLETED` ou `PROCESSING` |
| `total` | Vira `net_price` — o valor realmente pago |
| `billing.{email,first_name,last_name,phone}` | `ProfileService::findOrCreate()` |
| `line_items[].sku` | `createCart()` tira o `#`, deixa minúsculo e faz `explode('-')` — um item de carrinho por codename |
| `line_items[].subtotal` / `.total` | A API deriva o preço unitário de `subtotal/quantity` e o desconto de `(1 - total/subtotal) * 100` |

`tests/Feature/PurchaseGatewayTest.php` contém uma **cópia literal** de
`createCart()` e roda o nosso payload por ela, provando o contrato sem
precisar da API no ar.

### Se um dia quiserem uma rota dedicada

Basta apontar `LA_PURCHASE_ENDPOINT` para ela (ex.: `/api/asaas/order`). O
formato do corpo não muda, então a rota nova pode reaproveitar
`WooCommerceController::webhook()` inteiro.

---

## Catálogo

`config/catalog.php` é a fonte única de produtos, variantes e preços. Textos
ficam em `lang/{pt_BR,en,es}/catalog.php`.

Cada variante declara `codenames`: a lista de bundles que ela entrega. O SKU
enviado à API é essa lista unida por `-`.

| Produto | Variante | SKU enviado | De | Por |
|---|---|---|---|---|
| BIG5 — Mapa de Personalidade | — | `big5` | 247,70 | 197,00 |
| Mapa de Talentos & Habilidades | — | `talents` | 197,70 | 157,56 |
| O Melhor de Mim | com mapas | `best-big5-talents` | 997,70 | 797,64 |
| O Melhor de Mim | só o programa | `best` | 584,70 | 467,16 |
| O Melhor de Nós | + mapas | `bestus-big5-talents` | 612,70 | 489,60 |
| O Melhor de Nós | + MDM sem mapas | `best-bestus` | 612,00 | 489,60 |
| O Melhor de Nós | + MDM + mapas | `best-bestus-big5-talents` | 1.609,70 | 1.287,24 |
| BIG5 Single + Imersão | — | `big5single` | 124,70 | 99,70 |

`php artisan catalog:check` avisa se algum codename não existe ou está
soft-deleted na `la-app` — nos dois casos a compra entraria sem itens.

---

## Idiomas

`pt_BR`, `en` e `es`, escolhidos pela sessão com o `Accept-Language` como
palpite inicial. O site antigo não tinha tradução: usava o widget do Google
Language Translator. Aqui os três idiomas são conteúdo próprio.

Os documentos legais (`resources/legal/`) existem só em português e são
exibidos com aviso nos outros idiomas — traduzir cláusula de reembolso e de
LGPD é decisão jurídica, não de implementação.

Um teste garante que os três arquivos de idioma têm exatamente as mesmas
chaves.

---

## Produção

```sh
php artisan config:cache route:cache view:cache
npm run build
php artisan queue:work --tries=8      # obrigatório
```

`QUEUE_CONNECTION=sync` **não serve**: a entrega roda dentro da requisição do
webhook e, se a `la-app` estiver fora do ar, o Asaas recebe erro e fica
reenviando o evento.

Pedidos com `delivery_status = failed` são compras pagas que não chegaram à
`la-app` — vale um alerta em cima dessa coluna.
