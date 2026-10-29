<?php
use App\Enums\RunStatus;

it('lets a pending run start running', function () {
    expect(RunStatus::Pending->canTransitionTo(RunStatus::Running))->toBeTrue();
});

it('lets a running run to completed', function () {
    expect(RunStatus::Running->canTransitionTo(RunStatus::Completed))->toBeTrue();
});

it('does not let a completed run start again', function () {
    expect(RunStatus::Completed->canTransitionTo(RunStatus::Running))->toBeFalse();
});

it('lets a waiting_approval run to running', function () {
    expect(RunStatus::WaitingApproval->canTransitionTo(RunStatus::Running))->toBeTrue();
});

it('does not let a pending run to completed', function () {
    expect(RunStatus::Pending->canTransitionTo(RunStatus::Completed))->toBeFalse();
});