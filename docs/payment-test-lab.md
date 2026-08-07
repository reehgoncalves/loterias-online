# Laboratório de testes de pagamentos

Este laboratório valida o fluxo de checkout sem criar cobranças reais. Ele é
reproduzível localmente e também roda no GitHub Actions. Antes de alterar
qualquer parte de pagamento, leia [`../.agents/payment-rules.md`](../.agents/payment-rules.md)
e [`../.agents/coupon-rules.md`](../.agents/coupon-rules.md).

## O que é coberto

- checkout de pedido com cartão e idempotência por `Idempotency-Key`;
- rejeição explícita de boleto enquanto o método está desativado;
- criação de uma sessão Stripe com chave falsa e `Http::fake()`;
- webhook duplicado sem duplicar ledger, aposta ou e-mail;
- assinatura Stripe inválida, timestamp fora da janela e assinatura válida;
- guard de risco que bloqueia a aposta quando a reserva elegível não cobre a
  exposição máxima;
- build do frontend e suíte completa da API.

## Rodar localmente

```bash
cd api
php artisan test --testsuite=Feature
cd ../frontend
npm run build
```

Os testes usam banco isolado e mocks HTTP. O cartão público de homologação
Stripe `4242 4242 4242 4242`, com validade futura e qualquer CVC, pode ser
usado somente no Checkout hospedado em modo `test`. Não use dados de cartão
real no laboratório.

## Teste manual no Stripe Dashboard

1. Confirme que o Dashboard está em **test mode**.
2. Abra o destino de webhook `Loterias Online API — pagamentos de teste`.
3. Envie apenas `checkout.session.completed` ou
   `payment_intent.succeeded` para
   `https://loterias-online-api.vercel.app/api/stripe/webhook`.
4. Confira a resposta e os logs da API; reenvie o mesmo evento para validar a
   idempotência.
5. Nunca use o botão de envio manual em live mode e nunca marque pedido como
   pago diretamente no banco.

O destino de teste configurado escuta os dois eventos acima e usa o segredo
`STRIPE_WEBHOOK_SECRET` exclusivamente no ambiente protegido do Vercel. O
segredo não deve aparecer em código, terminal, screenshot ou log.

## Critério para liberar produção

A publicação real só pode avançar quando a conexão PostgreSQL/Supabase estiver
configurada, as migrations forem executadas nesse banco, o webhook assinado
responder 2xx e a revisão jurídica/financeira autorizar o modelo. O guard de
risco deve permanecer fechado enquanto a reserva própria e os parâmetros de
pagamento de prêmios não estiverem aprovados. Testes verdes não substituem
capital, licença, KYC/AML ou conciliação financeira.
