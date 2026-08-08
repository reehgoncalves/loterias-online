<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CaixaResultsClient
{
    public function latest(string $slug): array
    {
        $url = rtrim(str_replace('{slug}', $slug, (string) env('LOTTERY_RESULTS_URL', 'https://servicebus2.caixa.gov.br/portaldeloterias/api/{slug}')), '/').'/';
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) throw new RuntimeException('Endpoint de resultados inválido.');
        $response = Http::timeout(15)
            ->retry(2, 250, throw: false)
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Origin' => 'https://loterias.caixa.gov.br',
                'Referer' => 'https://loterias.caixa.gov.br/',
                'User-Agent' => 'LoteriasOnline/1.0 (+https://loterias-online-alpha.vercel.app/)',
            ])
            ->get($url);
        if (! $response->successful()) throw new RuntimeException('Fonte de resultados indisponível: HTTP '.$response->status());
        $payload = $response->json();
        if (! is_array($payload) || empty($payload['numero'])) throw new RuntimeException('Resposta de resultado sem concurso válido.');
        $numbers = $payload['listaDezenas'] ?? $payload['dezenas'] ?? [];
        return [
            'contest_number' => (int) $payload['numero'],
            'draw_at' => $this->parseDate($payload['dataApuracao'] ?? now()->toDateTimeString()),
            'numbers' => array_values(array_map('intval', $numbers)),
            'special' => $payload['nomeTimeCoracao'] ?? $payload['nomeTimeCoracaoMesSorte'] ?? $payload['mesSorte'] ?? $payload['timeCoracao'] ?? null,
            'next_contest_number' => (int) ($payload['numeroConcursoProximo'] ?? 0),
            'next_draw_at' => $this->parseDate($payload['dataProximoConcurso'] ?? null),
            'raw' => $payload,
        ];
    }

    private function parseDate(?string $date): ?string
    {
        if (! $date) return null;

        $parsed = \DateTimeImmutable::createFromFormat('d/m/Y', trim($date));
        if ($parsed === false) {
            try { $parsed = new \DateTimeImmutable($date); } catch (\Throwable) { return null; }
        }

        return $parsed->format('Y-m-d H:i:s');
    }
}
