<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = ['workspace_id', 'name', 'instructions', 'tools', 'max_steps'];

    protected function casts(): array
    {
        return ['tools' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
