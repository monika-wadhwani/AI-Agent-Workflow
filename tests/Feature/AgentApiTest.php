<?php

use App\Enums\WorkspaceRole;
use App\Models\Agent;

it('lets an owner create an agent', function () {
    $workspace = workspace();
    actingAsMemberOf($workspace, WorkspaceRole::Owner);

    $this->postJson('/api/agents', [
        'name' => 'Report bot',
        'instructions' => 'Write short reports.',
        'tools' => ['http_get'],
        'max_steps' => 5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Report bot')
        ->assertJsonPath('data.tools', ['http_get']);

    actingInWorkspace($workspace);
    expect(Agent::count())->toBe(1);
});

it('stops a plain member from creating an agent', function () {
    $workspace = workspace();
    actingAsMemberOf($workspace, WorkspaceRole::Member);

    $this->postJson('/api/agents', ['name' => 'Nope', 'tools' => []])->assertForbidden();

    actingInWorkspace($workspace);
    expect(Agent::count())->toBe(0);
});

it('rejects an agent that asks for a tool that does not exist', function () {
    actingAsMemberOf(workspace(), WorkspaceRole::Owner);

    $this->postJson('/api/agents', ['name' => 'Bad', 'tools' => ['delete_everything']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tools.0']);
});

it('rejects an agent with no name', function () {
    actingAsMemberOf(workspace(), WorkspaceRole::Owner);

    $this->postJson('/api/agents', ['tools' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});