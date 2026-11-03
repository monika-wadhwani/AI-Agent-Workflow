<?php

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Run;

it('creates a run that starts as pending', function (){
    actingInWorkspace(workspace());
    $agent = Agent::create([
        'name' => 'Helper',
        'tools' => ['http_get'],
    ]);

    $run = Run::create([
        'agent_id' => $agent->id,
        'input' => 'Do the thing',
    ]);

    expect($run->fresh()->status)->toBe(RunStatus::Pending);
});