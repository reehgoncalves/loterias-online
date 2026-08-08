<?php

namespace App\Services;

use App\Jobs\SettleDrawBets;
use App\Models\Draw;
use App\Models\LotteryGame;
use InvalidArgumentException;

class LotteryResultImporter
{
    /**
     * Importa um resultado já normalizado por uma fonte confiável.
     *
     * O hash mantém a operação idempotente e evita reabrir um concurso já
     * liquidado quando o mesmo resultado for entregue novamente pelo worker.
     */
    public function import(LotteryGame $game, array $result): Draw
    {
        $contestNumber = (int) ($result['contest_number'] ?? 0);
        $numbers = array_values(array_map('intval', (array) ($result['numbers'] ?? [])));
        $drawAt = (string) ($result['draw_at'] ?? '');
        $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];

        if ($contestNumber < 1 || $numbers === [] || $drawAt === '') {
            throw new InvalidArgumentException('Resultado normalizado incompleto.');
        }

        $hash = hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = Draw::query()
            ->where('lottery_game_id', $game->id)
            ->where('contest_number', $contestNumber)
            ->first();

        $sameResult = $existing?->result_hash === $hash;
        $status = $sameResult && $existing?->status === 'settled'
            ? 'settled'
            : 'result_received';

        $draw = Draw::updateOrCreate(
            ['lottery_game_id' => $game->id, 'contest_number' => $contestNumber],
            [
                'draw_at' => $drawAt,
                'results' => [
                    'numbers' => $numbers,
                    'special' => $result['special'] ?? null,
                ],
                'raw_payload' => $raw,
                'result_hash' => $hash,
                'synced_at' => now(),
                'status' => $status,
                'payout_cap_cents' => $game->max_prize_cents,
            ],
        );

        if (! ($sameResult && $draw->status === 'settled')) {
            SettleDrawBets::dispatch($draw->id);
        }

        $this->syncNextDraw($game, $result, $contestNumber);

        return $draw->fresh(['game']);
    }

    private function syncNextDraw(LotteryGame $game, array $result, int $contestNumber): void
    {
        $nextContestNumber = (int) ($result['next_contest_number'] ?? 0);
        $nextDrawAt = $result['next_draw_at'] ?? null;

        if ($nextContestNumber <= $contestNumber || ! $nextDrawAt) return;

        Draw::query()
            ->where('lottery_game_id', $game->id)
            ->where('status', 'open')
            ->where('contest_number', '<', $nextContestNumber)
            ->update(['status' => 'closed']);

        Draw::updateOrCreate(
            ['lottery_game_id' => $game->id, 'contest_number' => $nextContestNumber],
            [
                'draw_at' => $nextDrawAt,
                'sales_close_at' => null,
                'status' => 'open',
                'payout_cap_cents' => $game->max_prize_cents,
            ],
        );
    }
}
