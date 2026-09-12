<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
     */
    protected function receiptUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->receipt_path
                ? Storage::disk('public')->url($this->receipt_path)
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
