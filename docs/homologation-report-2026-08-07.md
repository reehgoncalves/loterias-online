# Laudo de homologação — 07/08/2026

## Escopo

Foi validado um ambiente local isolado da produção, com SQLite, frontend Vue e
API Laravel separados. O fluxo de demonstração não usa banco de produção, não
cria cobrança real e não libera prêmio real.

- Frontend: `http://127.0.0.1:5176/`
- API: `http://127.0.0.1:8010/`
- Banco: `api/database/database.sqlite`
- Crédito de teste: R$ 5.000,00, disponível somente ao `RiskGuard` em ambiente
  local/test/staging
- Boleto: desativado
- Regra de risco: fail closed; uma reserva insuficiente bloqueia o checkout

## Roteiro manual executado no navegador

1. A home carregou com banners rotativos de bolões, jogos, valores, blocos
   visuais de casa/carro/dinheiro e depoimentos identificados como demonstração.
2. Foi criado pela plataforma um cliente de homologação e a sessão foi
   autenticada sem perder o carrinho.
3. Foi aberto **Jogos**, selecionado Mega-Sena e gerada uma Surpresinha pelo
   endpoint protegido de cupons. O cupom exibido foi `02 · 20 · 22 · 25 · 39 · 44`.
4. O jogo de R$ 5,00 foi adicionado ao carrinho.
5. Foi aberto **Bolões**, adicionada uma cota do bolão **Milionário da Semana**
   por R$ 12,50. O carrinho exibiu os dois itens e total de R$ 17,50.
6. O perfil do cliente abriu a área de pagamentos e a modal segura de cartão.
   Nesta instância local, como as chaves Stripe não estão gravadas, a modal
   exibiu o bloqueio explícito “cadastro de cartão ainda não está configurado
   neste ambiente”; nenhum dado de cartão foi solicitado ou armazenado.
7. O painel administrativo foi aberto e exibiu o selo **Homologação ativa**, o
   crédito simulado de R$ 5.000,00, caixa elegível, margem, passivo, saques,
   apostas e o gráfico de linha ApexCharts.

## Cenário automatizado ponta a ponta

O teste `api/tests/Feature/HomologationJourneyTest.php` encadeia o fluxo inteiro
com respostas Stripe simuladas por `Http::fake()`:

1. Cadastro do cliente e criação automática do Customer Stripe.
2. Listagem de cartão somente mascarado (`Visa final 4242`).
3. Criação de SetupIntent para cadastrar cartão pela modal da plataforma.
4. Pedido misto com jogo e uma cota de bolão, total de R$ 12,90.
5. Validação de cartão pertencente ao cliente e criação de PaymentIntent.
6. Webhook `payment_intent.succeeded` recebido duas vezes sem duplicar o
   ledger, a confirmação ou a cota vendida.
7. Resultado do concurso inserido como resultado recebido e liquidação
   idempotente do jogo vencedor.
8. Prêmio de R$ 10,00 colocado em `manual_review`.
9. Admin aprova a simulação em homologação e o valor entra na carteira do
   cliente.
10. Cliente solicita saque PIX de teste e o admin baixa a solicitação em modo
    simulado; nenhuma transferência bancária real é feita.
11. Dashboard financeiro confere receita de R$ 12,90, prêmio de R$ 10,00,
    margem de R$ 2,90 e crédito de teste de R$ 5.000,00.

## Resultado dos testes

Comandos executados:

```bash
cd api
php artisan test --testsuite=Feature

cd ../frontend
npm run build
```

Resultado atual:

- 25 testes passaram;
- 133 asserções passaram;
- 1 teste foi pulado: `StripeLiveSmokeTest`, pois a chave `sk_test_` não está
  presente no `.env` local;
- build Vue/Vite passou;
- build emitiu apenas aviso de chunk grande do ApexCharts, sem falha.

## Controles confirmados

- idempotência por pedido e webhook;
- validação de valor e moeda no webhook;
- validação de titularidade do cartão salvo;
- boleto rejeitado;
- PIX assíncrono só confirma após webhook;
- falha de pagamento cancela o pedido e libera cotas reservadas;
- resultado e liquidação idempotentes;
- prêmio aguarda revisão, KYC e janela de conferência;
- crédito de carteira e saque possuem ledger e revisão manual;
- `RISK_TEST_MODE` só é aceito fora de produção;
- produção mantém o bloqueio anti-prejuízo.

## Pendência para homologação Stripe pública

O Preview público da Vercel permanece protegido por autenticação, e a API de
Preview também está protegida. O navegador, portanto, não consegue fazer o
fetch cross-origin do frontend para a API sem uma sessão de proteção da Vercel;
remover essa proteção no projeto que contém produção não é seguro.

Para a homologação pública real, ainda é necessário um projeto de staging
separado com banco Supabase separado, `sk_test_`, `pk_test_` e webhook de teste
próprios. O smoke test real então deve ser executado com cartão Stripe `4242`
e PIX somente se o método estiver habilitado na conta de teste. Nenhuma dessas
pendências autoriza criar transações fictícias em produção.

## Conclusão

O núcleo funcional e os controles de segurança passaram no laboratório isolado,
e o painel administrativo ficou disponível no navegador para inspeção. A
homologação foi aprovada para testes internos simulados; a liberação pública
com Stripe real de teste depende exclusivamente do staging cloud separado,
suas credenciais `sk_test_` e webhook, além da revisão legal/financeira.
