# Regras imutáveis de pagamentos, risco e liquidação

Este documento é uma barreira de segurança do produto. Ele deve ser lido antes
de qualquer alteração em pagamentos, apostas, bolões, prêmios, carteira,
estornos, jobs, webhooks, limites ou relatórios financeiros.

## Princípio central

O produto deve operar em modo **fail closed**. Se houver dúvida, atraso,
divergência, indisponibilidade do provedor, resultado não confirmado, saldo
insuficiente ou cálculo inconclusivo, a operação fica bloqueada e nenhum valor
é pago automaticamente.

Não é permitido tratar o valor exibido na tela, uma cobrança criada, um boleto
gerado, um Pix pendente ou um cartão autorizado como dinheiro disponível. Só é
disponível o valor confirmado pelo provedor, conciliado, sem estorno/chargeback
pendente e dentro da janela de risco definida.

## Regras de aceite de aposta

1. Toda aposta precisa pertencer a um jogo e concurso aberto.
2. A aposta precisa ser criada com preço calculado no servidor. O cliente nunca
   escolhe o preço final.
3. A aposta só pode virar `paid` depois de webhook idempotente de pagamento
   confirmado.
4. Pagamentos `pending`, `requires_action`, `boleto_pending` e `pix_pending`
   não aumentam o caixa disponível.
5. O sistema deve reservar a exposição máxima antes de aceitar uma nova aposta.
6. A exposição máxima de um concurso deve ser menor ou igual ao caixa elegível
   reservado para aquele concurso, descontando taxas, estornos esperados e uma
   margem de segurança.
7. Se a conta não tiver reserva suficiente, a API deve rejeitar a aposta com
   erro explícito. Nunca aceitar primeiro e “ver depois”.
8. Devem existir limites por aposta, cliente, jogo, concurso, dia e bolão.
9. A reserva deve ser calculada dentro de transação com lock de banco para
   evitar duas compras simultâneas ultrapassarem o limite.
10. Toda mudança de limite deve ser auditável e não pode diminuir a reserva de
    apostas já confirmadas.

## Fórmula conservadora de caixa elegível

```text
caixa_elegivel =
  pagamentos_confirmados
  - reembolsos_confirmados
  - chargebacks_e_disputas
  - taxas_do_provedor
  - impostos_e_custos_reservados
  - reserva_de_estorno
```

```text
limite_de_exposicao = caixa_elegivel * payout_ratio * safety_ratio
```

Por padrão, `payout_ratio` deve ser no máximo `0.70` e `safety_ratio` no
mínimo `0.80`. Esses valores são controles técnicos conservadores e não
substituem modelagem atuarial ou autorização legal. Para uma operação sem
capital próprio, a regra recomendada é `payout_ratio = 0.00` até existir uma
estrutura legal e financeira aprovada.

## Regras de prêmio e liquidação

1. O resultado deve vir de fonte oficial configurada e ser armazenado com
   payload bruto, horário, hash e status de sincronização.
2. Resultado ausente, alterado, duplicado ou divergente bloqueia a liquidação.
3. A liquidação deve ser idempotente por concurso. Reprocessar um job nunca
   pode criar um segundo pagamento.
4. O cálculo precisa usar os números registrados no momento da compra, nunca
   os números da tela atual.
5. O prêmio calculado é o menor valor entre: regra do jogo, teto do concurso,
   teto da aposta/bolão e caixa elegível reservado.
6. Se o prêmio não couber na reserva, o status fica `manual_review` e nenhum
   valor é enviado automaticamente.
7. Nunca prometer prêmio, multiplicador ou retorno fixo sem regra publicada e
   validação jurídica.
8. Prêmio aprovado deve primeiro criar lançamento imutável no ledger; só então
   pode gerar solicitação de pagamento.
9. Pagamento ao ganhador exige identidade/KYC, titularidade da conta e revisão
   de fraude/AML conforme o modelo autorizado.
10. Nenhum prêmio pode ser pago para conta de terceiro sem validação e
    autorização documentada.

## Regras de Stripe e métodos de pagamento

No lançamento atual, a API e a interface aceitam somente cartão e PIX. Boleto
fica desativado até existir homologação bancária, confirmação de liquidação e
revisão de risco; não reative esse método removendo apenas a validação da tela.

1. Segredos Stripe só no backend; nunca no frontend, Git ou logs.
2. Usar `sk_test_` em homologação e separar chaves/webhooks de produção.
3. Webhook deve validar assinatura, timestamp e idempotência.
4. O cliente não pode confirmar pagamento apenas pelo retorno do navegador.
5. Pix só entra no caixa após evento confirmado. Considerar risco de disputa e
   manter reserva.
6. Boleto só entra no caixa depois da confirmação bancária; boleto emitido não
   é saldo.
7. Cartão precisa considerar falha, contestação, reembolso e chargeback.
8. Estorno deve bloquear valor equivalente no ledger antes da solicitação.
9. Não usar Stripe Connect/payout para pagar prêmios automaticamente sem
   validar se a conta, o país, o produto e o modelo de negócio são elegíveis.
10. Toda falha do Stripe deve resultar em estado pendente/manual, nunca em
    aposta paga ou prêmio liberado.

## Ledger e auditoria

- Valores devem ser inteiros em centavos (`*_cents`), nunca float.
- Ledger é append-only: correções são novos lançamentos, jamais edição de
  histórico.
- Cada lançamento deve ter `idempotency_key`, origem, usuário, valor, moeda,
  status e timestamps.
- Somas exibidas no admin precisam vir do ledger/consultas consistentes e
  mostrar a data de atualização.
- Jobs e webhooks devem registrar tentativas, erro, payload sanitizado e
  correlation id sem dados sensíveis.

## Bloqueios obrigatórios

Bloquear novas apostas e pagamentos automáticos quando ocorrer qualquer um:

- caixa elegível abaixo da exposição máxima;
- webhook inválido ou fora da janela;
- resultado sem confirmação oficial;
- diferença entre ledger e provedor;
- chargeback acima da reserva;
- falha de lock/transação;
- KYC pendente ou alerta de fraude;
- job duplicado ou concorrente;
- limite de jogo, concurso, cliente ou operador atingido;
- modo de produção sem variáveis de compliance preenchidas.

## Conteúdo e publicidade

Depoimentos, fotos e ganhos de demonstração devem ser rotulados como
`DEMONSTRAÇÃO` ou substituídos por material verídico com consentimento. Nunca
publicar depoimento falso como prova de ganho real.

## Aprovação de mudança

Qualquer relaxamento destas regras exige revisão humana explícita, documentação
da hipótese de risco, teste automatizado, migração reversível e validação
jurídica/financeira antes de produção.
