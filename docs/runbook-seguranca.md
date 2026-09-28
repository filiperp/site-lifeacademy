# Runbook de segurança — webhook de compra

Três pendências abertas na API (`la-app`, repositório separado). Todas exigem
acesso ao servidor de produção `api.lifeacademy.pro`.

O código já está corrigido e commitado (`61e59422` na `master` da `la-app`);
o que falta é operação.

---

## 1. Remover os dumps de PII do servidor

**Por quê.** A API gravava cada webhook em `Storage::disk('public')`, servido em
`APP_URL/storage`. O corpo traz nome, e-mail, telefone e endereço do comprador.

Confirmado em produção em 28/09/2026:

```
https://api.lifeacademy.pro/storage/2024-10-30-02-29-00-1371-woo.json          → 200
https://api.lifeacademy.pro/storage/2024-10-30-02-29-00-1371-headers_woo.json  → 200
```

A listagem de diretório está desligada (`/storage/` devolve 403), então os
arquivos não são varríveis às cegas — o nome tem data-hora ao segundo mais o id
do pedido. Quem tiver ou adivinhar uma URL, porém, baixa o conteúdo inteiro.

**Agravante:** o arquivo `headers_woo.json` contém o header
`x-wc-webhook-signature`. Juntando os dois arquivos, obtém-se um par
(payload, assinatura) válido. Isso não revela o segredo — HMAC não é reversível
— mas permite **replay**: reenviar aquele payload assinado passa na validação
nova. Como `WooCommerceController::webhook()` desvia para
`restorePurchase()` quando o pedido já existe, um replay pode ressuscitar uma
compra cancelada ou estornada e reativar os tokens de acesso.

**Como resolver.** No servidor:

```sh
cd /var/www/api.lifeacademy.pro/la-app

# 1. Veja o que será apagado, e quanto.
ls -la storage/app/public/ | grep -E 'woo|headers_woo'
ls storage/app/public/ | grep -cE 'woo'

# 2. Guarde uma cópia fora do diretório público, se precisar para auditoria.
mkdir -p ~/webhook-backup
mv storage/app/public/*woo*.json ~/webhook-backup/

# 3. Confirme que a URL parou de responder.
curl -o /dev/null -w '%{http_code}\n' \
  https://api.lifeacademy.pro/storage/2024-10-30-02-29-00-1371-woo.json
# esperado: 404
```

Use `mv`, não `rm`: se algum desses arquivos for a única evidência de uma compra
que deu errado, apagar impede a investigação.

O código novo não grava mais nesse diretório (`WOOCOMMERCE_STORE_PAYLOADS=false`
por padrão, disco privado quando ligado, e recusa explícita ao disco `public`).

---

## 2. Conferir antes de ativar a validação de assinatura

**Por quê.** Ligar a validação sem conferir pode derrubar todas as vendas, se o
segredo do `.env` não for o mesmo configurado no painel do WooCommerce.

A própria API deixou a evidência: até agora ela gravava
`-woo-INVALID.json` quando a assinatura não batia e `-woo.json` quando batia.

```sh
cd /var/www/api.lifeacademy.pro/la-app
ls storage/app/public/ | grep -c 'INVALID'
```

- **0** → o segredo atual confere. Suba com `WOOCOMMERCE_ENFORCE_SIGNATURE=true`.
- **qualquer outro número** → suba com `WOOCOMMERCE_ENFORCE_SIGNATURE=false`
  primeiro. Nesse modo nada é bloqueado e as rejeições vão para o log:

  ```sh
  tail -f storage/logs/laravel.log | grep -i 'assinatura'
  ```

  Depois de algumas compras reais sem rejeição, mude para `true`.

No ambiente de desenvolvimento o resultado é 0, contra 6 webhooks válidos — o
que sugere que o segredo confere, mas isso precisa ser confirmado em produção.

---

## 3. Rotacionar o segredo

**Por quê.** O segredo antigo esteve em texto puro no repositório e assinaturas
válidas ficaram públicas (ver item 1). Rotacionar invalida toda assinatura
capturada e fecha a janela de replay.

Gere o novo segredo — 32 bytes em hexadecimal, o mesmo formato do anterior:

```sh
openssl rand -hex 32
```

Aplique **nos três lugares, na mesma janela**, senão as compras param:

| Onde | O quê |
|---|---|
| WooCommerce → Configurações → Avançado → Webhooks → o webhook de pedido | campo **Segredo** |
| `la-app` (produção) → `.env` | `WOOCOMMERCE_WEBHOOK_SECRET=` |
| `site-lifeacademy` → `.env` | `LA_WEBHOOK_SECRET=` |

Depois, na API:

```sh
php artisan config:clear && php artisan config:cache
```

E no site novo, para conferir que os dois lados combinam:

```sh
php artisan integrations:check
```

O site agora falha alto se `LA_WEBHOOK_SECRET` ficar vazio, em vez de assinar
com string vazia e perder a venda em silêncio.

> Enquanto o WooCommerce antigo e o site novo conviverem, os dois precisam do
> mesmo segredo, porque ambos entregam pela mesma rota.

---

## 4. Credenciais do Asaas

Bloqueado até haver acesso à plataforma.

Quando a chave chegar, no `.env` do site:

```
ASAAS_ENVIRONMENT=sandbox
ASAAS_API_KEY=<Painel Asaas > Integrações > Chave de API>
ASAAS_WEBHOOK_TOKEN=<você escolhe; o mesmo valor vai no painel>
```

Cadastre o webhook no painel do Asaas:

```
URL:     https://SEU-DOMINIO/webhooks/asaas
Token:   igual ao ASAAS_WEBHOOK_TOKEN
Versão:  v3
Eventos: PAYMENT_CONFIRMED, PAYMENT_RECEIVED, PAYMENT_REFUNDED,
         PAYMENT_DELETED, CHECKOUT_PAID, CHECKOUT_CANCELED, CHECKOUT_EXPIRED
```

Valide sem precisar de uma compra real:

```sh
php artisan integrations:check              # confere a configuração
php artisan integrations:check --checkout   # cria um checkout de teste no sandbox
```

O segundo devolve um link de pagamento do Asaas para abrir no navegador. Ele se
recusa a rodar se o ambiente estiver em `production`.

Só passe `ASAAS_ENVIRONMENT=production` depois de uma compra completa no
sandbox, com o webhook chegando e o pedido virando `paid`.

---

## Fora do escopo, mas do mesmo tipo

- `.env.prod` é versionado no git da `la-app` e contém segredos reais (Mailgun,
  Nova, chaves do WooCommerce). Mesmo problema que acabamos de corrigir, em
  outra porta.
- `deploy.php` traz a senha do MySQL em texto puro.
- As páginas de produto do WordPress atual têm injeção de spam SEO
  (`traditionrolex.com`), sinal de site comprometido. Some quando o WordPress
  sair do ar.
