<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'address',
        'phone',
        'receipt_footer',
        'is_active',
    ];
}
