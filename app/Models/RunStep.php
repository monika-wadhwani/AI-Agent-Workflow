<?php

namespace App\Models;

use App\Enums\StepStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunStep extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['run_id', 'position', 'tool_call_id', 'tool_name', 'input', 'output', 'status', 'expires_at'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'status' => StepStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
