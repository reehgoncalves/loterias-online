<?php

namespace App\Services;

use App\Models\LotteryGame;
use Illuminate\Validation\ValidationException;

class LotteryRules
{
    public function definition(LotteryGame $game): array
    {
        $configured = config('lottery.official_rules.'.$game->slug, []);
        $officialTable = $game->price_table ?: ($configured['price_table'] ?? [(int) $game->numbers_required => (int) $game->price_cents]);
        $sellingTable = $game->selling_price_table ?: $officialTable;
        $legacyDefault = (int) ($game->min_numbers ?? 0) === 1 && (int) ($game->max_numbers ?? 0) === 1 && (int) $game->numbers_required > 1;
        $definition = array_merge([
            'min_numbers' => $legacyDefault ? (int) $game->numbers_required : (int) ($game->min_numbers ?: $game->numbers_required),
            'max_numbers' => $legacyDefault ? (int) $game->numbers_required : (int) ($game->max_numbers ?: $game->numbers_required),
            'range_min' => (int) ($game->number_min ?? 1),
            'range_max' => (int) $game->range_max,
            'price_table' => $sellingTable,
            'official_price_table' => $officialTable,
            'special_type' => $game->selection_mode === 'columns' ? 'columns' : null,
            'columns' => (int) ($game->special_options['columns'] ?? 7),
        ], $configured);
        $definition['official_price_table'] = $officialTable;
        $definition['price_table'] = $sellingTable;
        return $definition;
    }

    public function minNumbers(LotteryGame $game): int { return (int) $this->definition($game)['min_numbers']; }
    public function maxNumbers(LotteryGame $game): int { return (int) $this->definition($game)['max_numbers']; }

    public function numberCount(array $numbers, LotteryGame $game): int
    {
        return $this->isColumns($game) ? count($this->flatten($numbers)) : count($numbers);
    }

    public function priceFor(LotteryGame $game, int $numberCount): int
    {
        $table = $this->definition($game)['price_table'];
        $price = $table[$numberCount] ?? $table[(string) $numberCount] ?? null;
        if ($price === null) throw ValidationException::withMessages(['numbers' => "A quantidade de números escolhida não tem preço configurado para {$game->name}."]);
        return (int) $price;
    }

    public function officialPriceFor(LotteryGame $game, int $numberCount): int
    {
        $table = $this->definition($game)['official_price_table'];
        $price = $table[$numberCount] ?? $table[(string) $numberCount] ?? null;
        if ($price === null) throw ValidationException::withMessages(['numbers' => "A quantidade de números escolhida não tem preço oficial configurado para {$game->name}."]);
        return (int) $price;
    }

    public function validate(LotteryGame $game, array $numbers): array
    {
        if ($this->isColumns($game)) return $this->validateColumns($game, $numbers);
        $numbers = array_values(array_map('intval', $numbers));
        $minCount = $this->minNumbers($game); $maxCount = $this->maxNumbers($game);
        if (count($numbers) < $minCount || count($numbers) > $maxCount) throw ValidationException::withMessages(['numbers' => "Escolha entre {$minCount} e {$maxCount} números para {$game->name}."]);
        $definition = $this->definition($game);
        foreach ($numbers as $number) if ($number < $definition['range_min'] || $number > $definition['range_max']) throw ValidationException::withMessages(['numbers' => "Os números de {$game->name} devem estar entre {$definition['range_min']} e {$definition['range_max']}."]);
        if (count(array_unique($numbers)) !== count($numbers)) throw ValidationException::withMessages(['numbers' => 'Esta modalidade não permite números repetidos.']);
        $this->priceFor($game, count($numbers));
        sort($numbers, SORT_NUMERIC);
        return array_values($numbers);
    }

    public function validateSpecial(LotteryGame $game, mixed $special): ?string
    {
        $type = $this->definition($game)['special_type'] ?? null;
        if ($type === 'team') { $value = trim((string) $special); if ($value === '') throw ValidationException::withMessages(['special_value' => 'Selecione o Time do Coração da Timemania.']); return $value; }
        if ($type === 'month') { $value = (int) $special; if ($value < 1 || $value > 12) throw ValidationException::withMessages(['special_value' => 'Selecione um Mês da Sorte válido.']); return (string) $value; }
        return $special === null ? null : (string) $special;
    }

    public function canonicalNumbers(LotteryGame $game, array $numbers): array
    {
        if (! $this->isColumns($game)) { sort($numbers, SORT_NUMERIC); return array_values($numbers); }
        return array_map(function ($column) { $column = array_values(array_map('intval', (array) $column)); sort($column, SORT_NUMERIC); return $column; }, $numbers);
    }

    public function isColumns(LotteryGame $game): bool { return ($this->definition($game)['special_type'] ?? null) === 'columns'; }
    public function flatten(array $numbers): array { return array_values(array_map('intval', array_merge(...array_map(fn ($number) => is_array($number) ? $number : [$number], $numbers)))); }

    private function validateColumns(LotteryGame $game, array $numbers): array
    {
        $columns = (int) $this->definition($game)['columns'];
        if (count($numbers) === $columns && ! is_array($numbers[0] ?? null)) $numbers = array_map(fn ($number) => [(int) $number], $numbers);
        if (count($numbers) !== $columns) throw ValidationException::withMessages(['numbers' => "Escolha números nas {$columns} colunas do {$game->name}."]);
        $normalized = []; $total = 0;
        foreach ($numbers as $column) {
            $column = array_values(array_map('intval', (array) $column)); $count = count($column);
            if ($count < 1 || $count > 3 || count(array_unique($column)) !== $count || array_diff($column, range(0, 9)) !== []) throw ValidationException::withMessages(['numbers' => 'Cada coluna do Super Sete deve ter de 1 a 3 dígitos distintos entre 0 e 9.']);
            $normalized[] = $column; $total += $count;
        }
        if ($total < 7 || $total > 21 || ($total <= 14 && collect($normalized)->contains(fn ($column) => count($column) > 2)) || ($total >= 15 && collect($normalized)->contains(fn ($column) => count($column) < 2))) throw ValidationException::withMessages(['numbers' => 'No Super Sete, a quantidade total e a distribuição por coluna seguem a tabela oficial.']);
        $this->priceFor($game, $total);
        return $normalized;
    }
}
