<?php

namespace App\Services;

use App\Models\LotteryGame;
use Illuminate\Validation\ValidationException;

class CouponGenerator
{
    public function generateBatch(LotteryGame $game, int $quantity = 1): array
    {
        $quantity = max(1, min(50, $quantity));
        $coupons = [];
        $keys = [];
        $attempts = 0;

        while (count($coupons) < $quantity && $attempts++ < 5000) {
            $numbers = $this->generate($game);
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

    public function validate(LotteryGame $game, array $numbers): array
    {
        $numbers = array_values(array_map('intval', $numbers));
        if (count($numbers) !== (int) $game->numbers_required) {
            throw ValidationException::withMessages(['numbers' => "Escolha {$game->numbers_required} números para {$game->name}."]);
        }

        $min = (int) ($game->number_min ?? 1);
        $max = (int) $game->range_max;
        foreach ($numbers as $number) {
            if ($number < $min || $number > $max) {
                throw ValidationException::withMessages(['numbers' => "Os números de {$game->name} devem estar entre {$min} e {$max}."]);
            }
        }

        if (! $game->allow_repeated_numbers && count(array_unique($numbers)) !== count($numbers)) {
            throw ValidationException::withMessages(['numbers' => 'Esta modalidade não permite números repetidos.']);
        }

        return $game->selection_mode === 'columns' ? $numbers : $this->sorted($numbers);
    }

    public function canonicalKey(LotteryGame $game, array $numbers): string
    {
        $normalized = $game->selection_mode === 'columns' ? $numbers : $this->sorted($numbers);
        return $game->slug.':'.implode('-', array_map(fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT), $normalized));
    }

    private function generate(LotteryGame $game): array
    {
        $min = (int) ($game->number_min ?? 1);
        $max = (int) $game->range_max;
        $required = (int) $game->numbers_required;

        if ($game->selection_mode === 'columns' || $game->allow_repeated_numbers) {
            return array_map(fn (): int => random_int($min, $max), range(1, $required));
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

