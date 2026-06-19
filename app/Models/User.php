<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all shifts for this user.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Check if user is an owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if user is a kasir.
     */
    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    /**
     * Get the active (open) shift for this user.
     */
    public function activeShift()
    {
        return $this->shifts()->where('status', 'open')->first();
    }
}
