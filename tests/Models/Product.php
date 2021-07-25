<?php

namespace Based\TypeScript\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function getMixedAccessorAttribute()
    {
        //
    }

    public function getTypedAccessorAttribute(): ?string
    {
        return 'based-department';
    }

    public function getUnionTypedAccessorAttribute(): string | bool | null
    {
        return true;
    }
}
