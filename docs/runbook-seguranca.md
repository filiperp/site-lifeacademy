# Runbook de segurança — webhook de compra

Pendências que dependem de acesso externo. As três primeiras exigem o servidor
de produção `api.lifeacademy.pro`; a quarta, o painel da Hotmart.

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

## 4. Credenciais da Hotmart

O Asaas saiu do projeto; o checkout passou a ser da Hotmart.

Bloqueado até haver acesso ao painel. No `.env` do site:

```
HOTMART_HOTTOK=<Painel > Ferramentas > Webhook>
HOTMART_CLIENT_ID=<Hotmart Developers>
HOTMART_CLIENT_SECRET=<Hotmart Developers>
```

Cadastre o webhook no painel:

```
URL:     https://SEU-DOMINIO/webhooks/hotmart
Versão:  2.0.0
Eventos: PURCHASE_APPROVED, PURCHASE_REFUNDED, PURCHASE_CHARGEBACK,
         PURCHASE_CANCELED, PURCHASE_COMPLETE
```

Valide sem precisar de uma compra real:

```sh
php artisan integrations:check            # o que falta configurar e cadastrar
php artisan integrations:check --token    # testa as credenciais da API
```

O "Enviar teste" do painel bate na rota e responde `test ok` sem criar pedido —
o payload fica no log, que é como o formato real será confirmado.

> O hottok é, para a Hotmart, o que o segredo do item 3 é para o WooCommerce:
> a única coisa que separa uma venda real de um POST forjado numa rota que
> libera acesso. Trate com o mesmo cuidado.

## Fora do escopo, mas do mesmo tipo

- `.env.prod` é versionado no git da `la-app` e contém segredos reais (Mailgun,
  Nova, chaves do WooCommerce). Mesmo problema que acabamos de corrigir, em
  outra porta.
- `deploy.php` traz a senha do MySQL em texto puro.
- As páginas de produto do WordPress atual têm injeção de spam SEO
  (`traditionrolex.com`), sinal de site comprometido. Some quando o WordPress
  sair do ar.
