<?php

namespace App\Services;

use App\Models\LotteryGame;
use Illuminate\Validation\ValidationException;

class CouponGenerator
{
    public function __construct(private readonly LotteryRules $rules) {}

    public function generateBatch(LotteryGame $game, int $quantity = 1, ?int $numberCount = null): array
    {
        $quantity = max(1, min(50, $quantity));
        $coupons = [];
        $keys = [];
        $attempts = 0;

        while (count($coupons) < $quantity && $attempts++ < 5000) {
            $numbers = $this->generate($game, $numberCount);
            $key = $this->canonicalKey($game, $numbers);
            if (isset($keys[$key])) continue;
            $keys[$key] = true;
            $coupons[] = ['numbers' => $numbers, 'canonical_key' => $key];
        }

        if (count($coupons) !== $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Não foi possível gerar cupons únicos para este lote.']);
        }

        return $coupons;
    }

    public function validate(LotteryGame $game, array $numbers): array { return $this->rules->validate($game, $numbers); }

    public function canonicalKey(LotteryGame $game, array $numbers): string
    {
        $normalized = $this->rules->canonicalNumbers($game, $numbers);
        return $game->slug.':'.json_encode($normalized, JSON_THROW_ON_ERROR);
    }

    private function generate(LotteryGame $game, ?int $numberCount = null): array
    {
        $definition = $this->rules->definition($game);
        $min = (int) $definition['range_min'];
        $max = (int) $definition['range_max'];
        $required = $numberCount ?? $this->rules->minNumbers($game);
        if ($required < $this->rules->minNumbers($game) || $required > $this->rules->maxNumbers($game)) $required = $this->rules->minNumbers($game);

        if ($this->rules->isColumns($game)) {
            $columns = (int) ($definition['columns'] ?? 7);
            $columns = array_map(fn (): array => [random_int($min, $max)], range(1, $columns));
            $extra = max(0, $required - count($columns));
            while ($extra > 0) {
                foreach ($columns as $index => $column) {
                    if ($extra <= 0 || count($column) >= 3) continue;
                    $candidate = random_int($min, $max);
                    if (in_array($candidate, $column, true)) continue;
                    $columns[$index][] = $candidate; $extra--;
                }
            }
            return $columns;
        }

        $pool = range($min, $max);
        $selected = [];
        for ($index = count($pool) - 1; $index >= count($pool) - $required; $index--) {
            $pick = random_int(0, $index);
            [$pool[$pick], $pool[$index]] = [$pool[$index], $pool[$pick]];
            $selected[] = $pool[$index];
        }

        return $this->sorted($selected);
    }

    private function sorted(array $numbers): array
    {
        sort($numbers, SORT_NUMERIC);
        return array_values($numbers);
    }
}
