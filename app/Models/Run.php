<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Events\RunStatusChanged;
use App\Exceptions\InvalidRunTransition;
use App\Models\Agent;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


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

    public function steps(): HasMany
{
    return $this->hasMany(RunStep::class)->orderBy('position');
}

    /** The only way status should change: validated, saved, and announced. */
    public function transitionTo(RunStatus $to): void
    {
        $from = $this->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidRunTransition::between($from, $to);
        }

        $this->status = $to;
        $this->save();

        RunStatusChanged::dispatch($this, $from, $to);
    }
}
