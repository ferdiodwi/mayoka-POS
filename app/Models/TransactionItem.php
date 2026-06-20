<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'item_type', 'product_id', 'print_price_id',
        'addon_service_id', 'parent_item_id', 'description',
        'qty', 'unit_price', 'cost_price', 'discount', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function printPrice(): BelongsTo
    {
        return $this->belongsTo(PrintPrice::class);
    }

    public function addonService(): BelongsTo
    {
        return $this->belongsTo(AddonService::class);
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'parent_item_id');
    }

    public function childAddons(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'parent_item_id');
    }
}
