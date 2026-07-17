<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintPrice extends Model
{
    use \App\Traits\BelongsToBranch;

    protected $fillable = [
        'paper_size', 'color_type', 'side_type',
        'price_per_sheet', 'cost_per_sheet',
    ];

    protected function casts(): array
    {
        return [
            'price_per_sheet' => 'decimal:2',
            'cost_per_sheet' => 'decimal:2',
        ];
    }

    /**
     * Get the effective price for a given quantity.
     * Simplified: returns base price.
     */
    public function getPriceForQty(int $qty): string
    {
        return $this->price_per_sheet;
    }

    /**
     * Human-readable label for this price combination.
     */
    public function getLabelAttribute(): string
    {
        $color = $this->color_type === 'bw' ? 'Hitam Putih' : 'Warna';
        $side = $this->side_type === 'single' ? '1 Sisi' : 'Bolak-balik';
        return "{$this->paper_size} — {$color} — {$side}";
    }
}
