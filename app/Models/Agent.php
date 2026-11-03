<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['name', 'instructions', 'tools', 'max_steps'];

    protected function casts(): array
    {
        return ['tools' => 'array'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
