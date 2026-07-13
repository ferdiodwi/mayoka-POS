<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintPriceTier extends Model
{
    use \App\Traits\BelongsToBranch;

    protected $fillable = ['print_price_id', 'min_qty', 'price_per_sheet'];

    protected function casts(): array
    {
        return [
            'price_per_sheet' => 'decimal:2',
        ];
    }

    public function printPrice(): BelongsTo
    {
        return $this->belongsTo(PrintPrice::class);
    }
}
