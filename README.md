# Loterias Online

Plataforma nova, separada do `apetit-ops-hub`, com storefront em Vue 3 e API Laravel 12.

> Antes de alterar pagamentos, prêmios, apostas ou jobs, leia
> [`.agents/payment-rules.md`](.agents/payment-rules.md). Essas regras são
> obrigatórias e o backend deve falhar fechado quando a reserva não cobrir a
> exposição.

## O que já está estruturado

- catálogo com modalidades CAIXA, concursos e bolões;
- criação de apostas e carteira de apostas do cliente;
- carrinho persistente com cupons de jogos e cotas de bolão no mesmo pedido;
- cadastro rápido preservando o carrinho e área do cliente com portal seguro do
  Stripe para gerenciar cartão; PIX e boleto são escolhidos por checkout;
- checkout Stripe em modo test para cartão, Pix e boleto quando habilitados na conta;
- webhook de pagamento idempotente;
- sincronização configurável com o endpoint oficial de resultados CAIXA;
- job de liquidação automática de concursos;
- guard de risco: reserva, margem mínima e teto de exposição por concurso;
- painel administrativo com apostas, pagamentos, bolões, margem e exposição;
- gráficos de linha usando ApexCharts/Vue;
- cupons “surpresinha” server-side, com regras por modalidade e sem cupons
  duplicados no mesmo lote;
- e-mails transacionais e marketing responsivos com CTA, opt-in, descadastro e
  scheduler diário/próximo ao sorteio;
- contas seed de demonstração e depoimentos marcados como demonstrativos.

## Rodar localmente

```bash
cd api
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Em outro terminal:

```bash
cd frontend
npm install
npm run dev
```

Credenciais seed:

- Admin: `admin@loterias.online` / `Loterias@2026!`
- Cliente: `cliente@loterias.online` / `Loterias@2026!`

## Jobs e sincronização

```bash
cd api
php artisan lottery:sync
php artisan queue:work
php artisan schedule:work
```

Para habilitar os e-mails reais, troque `MAIL_MAILER=log` por SMTP/serviço de
envio e mantenha `marketing_opt_in=true` somente para clientes que deram
consentimento. O comando `marketing:send` não envia para opt-out e é idempotente
por cliente/concurso/template.

Em produção, use um worker persistente e o scheduler do provedor. O adapter de resultados usa `LOTTERY_RESULTS_URL` e mantém o endpoint configurável para acompanhar alterações do serviço oficial.

## Stripe

Copie as variáveis `STRIPE_*` do `api/.env.example`. Use somente chaves `sk_test_` durante homologação. O checkout é criado no backend para não expor segredo; o webhook atualiza o pagamento e libera a aposta apenas após confirmação.

Antes de aceitar dinheiro real, valide licença, KYC/AML, regras de publicidade, tributação, proteção de dados e autorização aplicável ao modelo de negócio. Os limites de risco implementados são controles técnicos, não substituem compliance jurídico ou financeiro.

## Publicação

O frontend está pronto para Vercel com `frontend/vercel.json`. O banco está preparado para PostgreSQL/Supabase via `DB_CONNECTION=pgsql`. A publicação no GitHub/Vercel/Supabase depende de uma sessão autenticada disponível ou de tokens fornecidos no ambiente.
