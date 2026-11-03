<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Run extends Model
{
    use BelongsToWorkspace;
    protected $fillable = ['agent_id', 'input', 'output', 'error', 'status'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => RunStatus::class];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
