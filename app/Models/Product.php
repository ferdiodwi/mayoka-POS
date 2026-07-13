<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use \App\Traits\BelongsToBranch;

    protected $fillable = [
        'category_id', 'name', 'barcode', 'type',
        'cost_price', 'stock', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('level');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBarang(Builder $query): Builder
    {
        return $query->where('type', 'barang');
    }

    public function scopeJasa(Builder $query): Builder
    {
        return $query->where('type', 'jasa');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('type', 'barang')
            ->whereColumn('stock', '<=', 'min_stock');
    }
}
