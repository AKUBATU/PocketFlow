<?php

namespace App\Services;

use Carbon\Carbon;

class TransactionTextParser
{
    public function parse(string $text): array
    {
        $cleanText = $this->normalizeText($text);

        return [
            'amount' => $this->extractAmount($cleanText),
            'transaction_date' => $this->extractDate($cleanText),
            'transaction_time' => $this->extractTime($cleanText),
            'merchant' => $this->extractMerchant($cleanText),
            'type_suggestion' => $this->suggestType($cleanText),
            'raw_text' => $text,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim($text);
    }

    private function extractAmount(string $text): ?float
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $priorityKeywords = [
            'grand total', 'total bayar', 'total pembayaran', 'total belanja',
            'total', 'jumlah', 'nominal', 'amount', 'paid', 'bayar', 'cash', 'debit', 'kredit'
        ];

        $candidates = [];

        foreach ($lines as $line) {
            $lower = mb_strtolower($line);
            preg_match_all('/(?:rp\s*)?([0-9]{1,3}(?:[\.,][0-9]{3})+(?:,[0-9]{2})?|[0-9]{4,})(?:\s*rp)?/i', $line, $matches);

            foreach ($matches[1] ?? [] as $rawNumber) {
                $value = $this->toNumber($rawNumber);
                if ($value === null || $value <= 0) {
                    continue;
                }

                $score = $value;
                foreach ($priorityKeywords as $keyword) {
                    if (str_contains($lower, $keyword)) {
                        $score += 100000000;
                        break;
                    }
                }

                $candidates[] = [
                    'value' => $value,
                    'score' => $score,
                    'line' => $line,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        return round((float) $candidates[0]['value'], 2);
    }

    private function toNumber(string $raw): ?float
    {
        $raw = preg_replace('/[^0-9\.,]/', '', $raw);
        if ($raw === '') {
            return null;
        }

        // Format Indonesia: 1.234.567,89
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            return (float) $raw;
        }

        // Format internasional: 1,234,567.89
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)) {
            $raw = str_replace(',', '', $raw);
            return (float) $raw;
        }

        // Angka plain atau desimal ringan.
        $raw = str_replace(',', '.', $raw);
        return is_numeric($raw) ? (float) $raw : null;
    }

    private function extractDate(string $text): ?string
    {
        $patterns = [
            '/\b(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})\b/',
            '/\b(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})\b/',
            '/\b(\d{1,2})[-\/](\d{1,2})[-\/](\d{2})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                try {
                    if (strlen($m[1]) === 4) {
                        return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->toDateString();
                    }

                    $year = (int) $m[3];
                    if ($year < 100) {
                        $year += 2000;
                    }

                    return Carbon::createFromDate($year, (int) $m[2], (int) $m[1])->toDateString();
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return now()->toDateString();
    }

    private function extractTime(string $text): ?string
    {
        if (preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)(?::([0-5]\d))?\b/', $text, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return now()->format('H:i:s');
    }

    private function extractMerchant(string $text): ?string
    {
        $lines = array_values(array_filter(array_map(function ($line) {
            $line = trim($line);
            $line = preg_replace('/[^\pL\pN\s\.&\-]/u', '', $line);
            return trim($line);
        }, explode("\n", $text))));

        foreach ($lines as $line) {
            if (mb_strlen($line) >= 3 && !preg_match('/\d{4,}/', $line)) {
                return mb_substr($line, 0, 80);
            }
        }

        return null;
    }

    private function suggestType(string $text): string
    {
        $lower = mb_strtolower($text);
        $incomeWords = ['transfer masuk', 'uang masuk', 'deposit', 'gaji', 'salary', 'penerimaan', 'received'];

        foreach ($incomeWords as $word) {
            if (str_contains($lower, $word)) {
                return 'income';
            }
        }

        return 'expense';
    }
}
