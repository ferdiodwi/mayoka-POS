<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'user_id', 'expense_date', 'category', 'amount', 'description', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function categoryLabels(): array
    {
        return [
            'listrik' => 'Listrik',
            'sewa' => 'Sewa Ruko',
            'gaji' => 'Gaji Karyawan',
            'operasional' => 'Operasional',
            'bahan_baku' => 'Bahan Baku',
            'lainnya' => 'Lainnya',
        ];
    }
}
