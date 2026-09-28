# Site Life Academy

Recriação do site lifeacademy.pro em Laravel 13, substituindo o WordPress +
WooCommerce + Elementor por uma vitrine própria com checkout na Hotmart.

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
| `HOTMART_HOTTOK` | Painel Hotmart → Ferramentas → Webhook | O webhook não autentica e **nenhuma compra libera acesso** |
| `HOTMART_CLIENT_ID` / `_SECRET` | Hotmart Developers → suas credenciais | `hotmart:sync` não roda; o webhook funciona sem |
| `LA_WEBHOOK_SECRET` | O mesmo `WOOCOMMERCE_WEBHOOK_SECRET` da `la-app` | Toda compra é recusada pela API |
| `LA_API_URL` | URL pública da `la-app` | Sem destino para a compra |

O site **não processa pagamento**. Cartão, Pix e boleto acontecem no checkout
da Hotmart, que também define preço e parcelamento por oferta.

### Cadastro na Hotmart

Cada variante do catálogo precisa de uma oferta:

```
Produto → Oferta → SKU = codenames unidos por "-"   (ex.: best-big5-talents)
```

O SKU é o caminho mais robusto de mapeamento: o webhook o traz em
`data.product.sku`, e com ele a compra é resolvida mesmo que alguém recrie a
oferta com outro código. `product_code` e `offer_code` em `config/catalog.php`
são o segundo caminho, e o que monta a URL do botão de compra.

`php artisan integrations:check` lista o que ainda falta cadastrar.

### Webhook a cadastrar no painel

```
URL:     https://SEU-DOMINIO/webhooks/hotmart
Versão:  2.0.0
Eventos: PURCHASE_APPROVED, PURCHASE_REFUNDED, PURCHASE_CHARGEBACK,
         PURCHASE_CANCELED, PURCHASE_COMPLETE
```

## Fluxo de compra

```
Vitrine → Página do produto → [Comprar]
                                  │
                                  ▼
                    pay.hotmart.com/<produto>?off=<oferta>
                                  │
                       cliente paga (cartão, Pix, boleto)
                                  │
              ┌───────────────────┴──────────────────┐
              ▼                                      ▼
   POST /webhooks/hotmart                   redirect para /obrigado
   (cria o pedido e confirma)               (só informativo)
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

O fluxo inverte em relação a um gateway: **não existe pedido antes do webhook**.
A compra acontece inteira na Hotmart, e a rota `/webhooks/hotmart` é a única
fonte da venda — por isso ela valida o hottok antes de qualquer coisa.

Um reembolso volta pelo mesmo caminho: `PURCHASE_REFUNDED` reenvia a compra à
`la-app` com status `refunded`, e `cancelPurchase()` revoga os tokens.

## Por que a API não precisou mudar

`POST /api/woocommerce/order` já faz tudo o que uma compra nova exige:
localiza ou cria o usuário, registra a compra, gera os tokens de acesso e
dispara o e-mail de boas-vindas. Ela só espera o corpo no formato do webhook
do WooCommerce.

O site monta exatamente esse corpo (`app/Services/LifeAcademy/PurchaseGateway.php`):

| Campo enviado | O que a API faz com ele |
|---|---|
| `id`, `number`, `order_key` | Colunas UNIQUE de `woocommerce_purchases`. Prefixamos com `HM-`/`la_` para nunca colidir com os IDs numéricos herdados do WooCommerce |
| `total` | Vira `net_price` — o valor realmente pago |
| `billing.{email,first_name,last_name,phone}` | `ProfileService::findOrCreate()` |
| `line_items[].sku` | `createCart()` tira o `#`, deixa minúsculo e faz `explode('-')` — um item de carrinho por codename |
| `status` | `completed` libera (a API só processa `COMPLETED`/`PROCESSING`); `refunded` e `cancelled` revogam o acesso |
| `line_items[].subtotal` / `.total` | A API deriva o preço unitário de `subtotal/quantity` e o desconto de `(1 - total/subtotal) * 100` |

`tests/Feature/PurchaseGatewayTest.php` contém uma **cópia literal** de
`createCart()` e roda o nosso payload por ela, provando o contrato sem
precisar da API no ar.

### Se um dia quiserem uma rota dedicada

Basta apontar `LA_PURCHASE_ENDPOINT` para ela (ex.: `/api/hotmart/order`). O
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

### Preço

Quem define o preço é a oferta na Hotmart. `php artisan hotmart:sync` lê as
ofertas pela API e reescreve `config/catalog.php`, para o site não anunciar um
valor e o checkout cobrar outro. Rode no deploy e num cron diário.

Variações acima de `HOTMART_SYNC_ALERT_THRESHOLD` (25% por padrão) são
sinalizadas em vez de aplicadas — uma queda de 90% costuma ser erro de
cadastro, não promoção. `--force` aplica mesmo assim, `--dry-run` só mostra.

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
webhook e, se a `la-app` estiver fora do ar, a Hotmart recebe erro e fica
reenviando o evento.

Pedidos com `delivery_status = failed` são compras pagas que não chegaram à
`la-app` — vale um alerta em cima dessa coluna.
