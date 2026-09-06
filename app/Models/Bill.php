<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Bill extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'owner_label',
        'responsible_user_id',
        'category_id',
        'scope',
        'recurrence',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the bill is not paid and past its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid'
            && $this->due_date !== null
            && $this->due_date->isBefore(Carbon::today());
    }

    /**
     * Normalize the effective status taking the due date into account.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        return $this->isOverdue() ? 'overdue' : 'pending';
    }
}
