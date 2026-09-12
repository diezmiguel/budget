<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReceiptScanService
{
    /**
     * Read a receipt image with OpenAI vision and return suggested expense fields.
     *
     * @param  array<int, array{id:int, name:string}>  $categories  Known categories to match against.
     * @return array{description: ?string, amount: ?float, spent_on: ?string, payment_method: ?string, category_id: ?int, raw_merchant: ?string}
     *
     * @throws RuntimeException when the API is not configured or the call fails.
     */
    public function scan(UploadedFile $image, array $categories = []): array
    {
        $key = config('services.openai.key');

        if (empty($key)) {
            throw new RuntimeException('O reconhecimento de recibos não está configurado (OPENAI_API_KEY em falta).');
        }

        $dataUrl = 'data:'.$image->getMimeType().';base64,'.base64_encode(
            file_get_contents($image->getRealPath())
        );

        $categoryList = collect($categories)
            ->map(fn ($c) => "{$c['id']}: {$c['name']}")
            ->implode(', ');

        $today = now()->toDateString();

        $instructions = <<<PROMPT
        You extract structured data from a photo of a purchase receipt.
        Return ONLY a JSON object with these exact keys:
        - "description": a short human label for the expense, ideally the merchant/store name (string or null)
        - "amount": the final total paid as a number using a dot decimal separator, no currency symbol (number or null)
        - "spent_on": the purchase date in strict YYYY-MM-DD format (string or null). If the year is missing assume the current year. Today is {$today}.
        - "payment_method": how it was paid if visible, e.g. "Cartão", "Dinheiro", "PIX" (string or null)
        - "category_id": the best matching category id from this list, or null if none fits. Categories: [{$categoryList}]
        Use null for any field you cannot read confidently. Do not guess the amount. Prefer the grand total over subtotals.
        PROMPT;

        try {
            $response = Http::withToken($key)
                ->timeout(45)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.receipt_model', 'gpt-4o-mini'),
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $instructions],
                                [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => $dataUrl, 'detail' => 'high'],
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Receipt scan HTTP error', ['message' => $e->getMessage()]);
            throw new RuntimeException('Não foi possível contactar o serviço de leitura de recibos.');
        }

        if ($response->failed()) {
            Log::warning('Receipt scan API failure', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('O serviço de leitura de recibos devolveu um erro.');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('Resposta inesperada do serviço de leitura de recibos.');
        }

        $parsed = json_decode($content, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('Não foi possível interpretar o recibo.');
        }

        return $this->normalize($parsed, $categories);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, array{id:int, name:string}>  $categories
     * @return array{description: ?string, amount: ?float, spent_on: ?string, payment_method: ?string, category_id: ?int, raw_merchant: ?string}
     */
    private function normalize(array $parsed, array $categories): array
    {
        $amount = $parsed['amount'] ?? null;
        if (is_string($amount)) {
            // Tolerate "1.234,56" or "1234,56" style output.
            $amount = str_replace(['.', ' '], ['', ''], $amount);
            $amount = str_replace(',', '.', $amount);
        }
        $amount = is_numeric($amount) ? round((float) $amount, 2) : null;

        $date = $parsed['spent_on'] ?? null;
        if (is_string($date) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = null;
        }

        $validCategoryIds = collect($categories)->pluck('id')->all();
        $categoryId = $parsed['category_id'] ?? null;
        $categoryId = (is_numeric($categoryId) && in_array((int) $categoryId, $validCategoryIds, true))
            ? (int) $categoryId
            : null;

        $description = isset($parsed['description']) && is_string($parsed['description'])
            ? trim($parsed['description']) ?: null
            : null;

        $paymentMethod = isset($parsed['payment_method']) && is_string($parsed['payment_method'])
            ? trim($parsed['payment_method']) ?: null
            : null;

        return [
            'description' => $description,
            'amount' => $amount,
            'spent_on' => $date,
            'payment_method' => $paymentMethod ? mb_substr($paymentMethod, 0, 40) : null,
            'category_id' => $categoryId,
            'raw_merchant' => $description,
        ];
    }
}
