<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tiers(): HasMany
    {
        return $this->hasMany(PrintPriceTier::class)->orderBy('min_qty');
    }

    /**
     * Get the effective price for a given quantity (considering tier discounts).
     */
    public function getPriceForQty(int $qty): string
    {
        $tier = $this->tiers()
            ->where('min_qty', '<=', $qty)
            ->orderBy('min_qty', 'desc')
            ->first();

        return $tier ? $tier->price_per_sheet : $this->price_per_sheet;
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
