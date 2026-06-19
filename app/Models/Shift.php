<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'cash_start',
        'cash_end',
        'cash_expected',
        'cash_difference',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'cash_start' => 'decimal:2',
            'cash_end' => 'decimal:2',
            'cash_expected' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    /**
     * Get the user (kasir) that owns this shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only active (open) shifts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get only closed shifts.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    /**
     * Check if this shift is currently open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
