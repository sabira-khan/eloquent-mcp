<?php

namespace SabiraKhan\EloquentMcp\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $hidden = ['password'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }
}
