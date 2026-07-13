<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use \App\Traits\BelongsToBranch;

    protected $fillable = [
        'product_id', 'level', 'unit_name', 'qty_per_previous', 'base_multiplier',
        'price_h1', 'price_h2', 'price_h3',
    ];

    protected function casts(): array
    {
        return [
            'price_h1' => 'decimal:2',
            'price_h2' => 'decimal:2',
            'price_h3' => 'decimal:2',
            'level' => 'integer',
            'qty_per_previous' => 'integer',
            'base_multiplier' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
