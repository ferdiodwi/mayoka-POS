<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \App\Traits\BelongsToBranch;

    protected $fillable = ['code', 'name', 'type', 'price_level', 'phone', 'address', 'is_active'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMembers($query)
    {
        return $query->where('type', 'member');
    }
}
