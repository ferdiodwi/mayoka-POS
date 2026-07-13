<?php

namespace App\Traits;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBranch
{
    /**
     * Boot the trait and apply the global scope.
     */
    protected static function bootBelongsToBranch()
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            // Apply branch scope if a branch_id is set in the configuration/context.
            // This will be set by the EnsureBranchScope middleware.
            $branchId = config('app.active_branch_id');
            if ($branchId) {
                $builder->where($builder->getQuery()->from . '.branch_id', $branchId);
            }
        });
        
        static::creating(function ($model) {
            if (!$model->branch_id) {
                $model->branch_id = config('app.active_branch_id') ?? 1; // Default to 1 (Pusat) if null
            }
        });
    }

    /**
     * Get the branch that owns the model.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
