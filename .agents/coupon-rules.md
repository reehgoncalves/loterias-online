# Regras obrigatórias para cupons e números

1. A seleção aleatória deve usar `random_int()` no backend ou fonte
   criptograficamente segura equivalente.
2. Todos os números válidos precisam ter a mesma probabilidade. Não usar
   números “quentes”, “frios”, datas de aniversário ou histórico para
   aumentar artificialmente a chance.
3. A geração pode evitar cupons idênticos dentro do mesmo pedido/lote, mas isso
   não muda a probabilidade oficial do jogo e não garante que não haverá mais
   de um ganhador.
4. A validação deve respeitar exatamente a modalidade: faixa, quantidade,
   repetição, colunas e números especiais.
5. O cupom final deve ser imutável depois do pagamento confirmado. Alterações
   criam um novo cupom e passam novamente pelas regras de risco.
6. A interface deve informar que “surpresinha” é uma seleção aleatória e não
   promessa de prêmio ou estratégia vencedora.
7. O servidor calcula a combinação e o preço; o frontend não pode impor
   números, valor ou resultado.
8. Cupons gerados para demonstração devem ser marcados como demonstração e
   nunca misturados com apostas pagas.

