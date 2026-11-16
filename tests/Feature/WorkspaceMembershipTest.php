<?php

use App\Enums\WorkspaceRole;
use App\Models\User;

it('lets a user belong to a workspace with a role', function () {
    $workspace = workspace();
    $user = userIn($workspace, WorkspaceRole::Owner);

    expect($user->roleIn($workspace))->toBe(WorkspaceRole::Owner)
        ->and($workspace->members)->toHaveCount(1);
});

it('says a user has no role in a workspace they do not belong to', function () {
    $user = User::factory()->create();

    expect($user->roleIn(workspace()))->toBeNull();
});

it('lets one user belong to many workspaces', function () {
    $user = User::factory()->create();
    $alpha = workspace('Alpha');
    $beta = workspace('Beta');
    $alpha->members()->attach($user, ['role' => WorkspaceRole::Member->value]);
    $beta->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    expect($user->workspaces)->toHaveCount(2)
        ->and($user->roleIn($beta))->toBe(WorkspaceRole::Owner)
        ->and($user->roleIn($alpha))->toBe(WorkspaceRole::Member);
});