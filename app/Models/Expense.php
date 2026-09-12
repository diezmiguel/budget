<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'description',
        'amount',
        'spent_on',
        'scope',
        'category_id',
        'user_id',
        'payment_method',
        'notes',
        'receipt_path',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'receipt_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }

    /**
     * Public URL for the receipt image, or null when none is attached.
     *
     * Served through an authenticated app route (not the /storage symlink),
     * so it works on shared hosting and keeps receipts private.
     */
    protected function receiptUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->receipt_path
                ? url("/api/expenses/{$this->id}/receipt")
                : null,
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
