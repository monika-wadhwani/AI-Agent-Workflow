<?php

use App\Enums\RunStatus;
use App\Events\RunStatusChanged;
use App\Exceptions\InvalidRunTransition;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => actingInWorkspace(workspace()));

it('moves a run to an allowed status and saves it', function (){
    $run = newRun(newAgent());

    $run->transitionTo(RunStatus::Running);

    expect($run->fresh()->status)->toBe(RunStatus::Running);
});

it('rejects a move the state machine does not allow', function () {
    $run = newRun(newAgent());

    $run->transitionTo(RunStatus::Completed);
})->throws(InvalidRunTransition::class);

it('annonces every status change with an event', function () {
    Event::fake([RunStatusChanged::class]);
    $run = newRun(newAgent());
    
    $run->transitionTo(RunStatus::Running);

    Event::assertDispatched(
        RunStatusChanged::class,
        fn ($e) => $e->from === RunStatus::Pending && $e->to === RunStatus::Running
    );
});