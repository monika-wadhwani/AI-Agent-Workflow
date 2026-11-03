<?php

use App\Models\Agent;
use App\Models\Run;
use App\Support\CurrentWorkspace;

it('only return data from the current workspace', function () {
    $alpha = workspace('Alpha');
    $beta = workspace('Beta');

    actingInWorkspace($alpha);
    newAgent();

    actingInWorkspace($beta);
    newAgent();
    newAgent();

    expect(Agent::count())->toBe(2);

    actingInWorkspace($alpha);
    expect(Agent::count())->toBe(1);
});

it('cannot load another workspace record by id', function () {
    actingInWorkspace(workspace('Alpha'));
    $run = newRun(newAgent());

    actingInWorkspace(workspace('Beta'));

    expect(Run::find($run->id))->toBeNull();
});

it('fails closed when no workspace is set', function () {
    actingInWorkspace(workspace());
    newAgent();

    app(CurrentWorkspace::class)->set(null);

    expect(Agent::count())->toBe(0);
});

it('refuses to create tenant data without a workspace', function(){
    app(CurrentWorkspace::class)->set(null);

    Agent::create(['name' => 'Orphan', 'tools' => []]);

})->throws(LogicException::class, 'No current workspace');

it('restores the previous workspace after runAs', function () {
    $alpha = workspace('Alpha');
    $beta = workspace('Beta');
    $current = app(CurrentWorkspace::class);
    $current->set($alpha);

    $inside = $current->runAs($beta, fn () => $current->id());

    expect($inside)->toBe($beta->id)
        ->and($current->id())->toBe($alpha->id);
});

it('keeps tenants apart when code runs as another workspace', function () {
    $alpha = workspace('Alpha');
    $beta = workspace('Beta');

    actingInWorkspace($alpha);
    $alphaRun = newRun(newAgent());

    app(CurrentWorkspace::class)->runAs($beta, function () use ($alphaRun) {
        expect(Run::find($alphaRun->id))->toBeNull();
    });
});