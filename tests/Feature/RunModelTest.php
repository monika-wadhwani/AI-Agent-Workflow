<?php

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Run;
use App\Models\Workspace;

it('creates a run that starts as pending', function (){
    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $agent = Agent::create([
        'workspace_id' => $workspace->id,
        'name' => 'Helper',
        'tools' => ['http_get'],
    ]);

    $run = Run::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'input' => 'Do the thing',
    ]);

    expect($run->fresh()->status)->toBe(RunStatus::Pending);
});