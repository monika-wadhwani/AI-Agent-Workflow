<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = ['name', 'slug'];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
