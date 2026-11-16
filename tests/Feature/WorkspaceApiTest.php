<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a request with no login', function () {
    $this->getJson('/api/workspace')->assertUnauthorized();
});

it('required the X-Workspace header', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/workspace')->assertStatus(400);
});

it('shows the workspace and the role of a member', function () {
    $workspace = workspace('Acme');
    Sanctum::actingAs(userIn($workspace, WorkspaceRole::Owner));

    $this->getJson('/api/workspace', ['X-Workspace' => $workspace->slug])
        ->assertOk()
        ->assertJson(['id' => $workspace->id, 'name' => 'Acme', 'role' => 'owner']);
});

it('refuses a workspace the user does not belong to', function () {
    $mine = workspace('Mine');
    $theirs = workspace('Theirs');
    Sanctum::actingAs(userIn($mine));

    $this->getJson('/api/workspace', ['X-Workspace' => $theirs->slug])->assertForbidden();
});

it('gives the same answer for a workspace that does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/workspace', ['X-Workspace' => 'no-such-workspace'])->assertForbidden();
});