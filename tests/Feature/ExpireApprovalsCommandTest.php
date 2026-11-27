<?php

use App\Enums\RunStatus;
use App\Models\Run;
use App\Support\CurrentWorkspace;
use Tests\Support\FakeTool;

beforeEach(function () {
    $this->workspace = workspace('Acme');
    actingInWorkspace($this->workspace);
});

it('fails runs whose approval window has passed', function () {
    $step = pausedStep(new FakeTool('risky', needsApproval: true));

    $this->travel(25)->hours();

    $this->artisan('agents:expire-approvals')
        ->expectsOutput('Expired 1 approval(s).')
        ->assertSuccessful();

    expect(Run::find($step->run_id)->status)->toBe(RunStatus::Failed);
});

it('leaves approvals that are still inside the window alone', function () {
    $step = pausedStep(new FakeTool('risky', needsApproval: true));

    $this->travel(1)->hours();

    $this->artisan('agents:expire-approvals')
        ->expectsOutput('Expired 0 approval(s).')
        ->assertSuccessful();

    expect(Run::find($step->run_id)->status)->toBe(RunStatus::WaitingApproval);
});

it('expires approvals in every workspace even though the scheduler has none', function () {
    $stepAcme = pausedStep(new FakeTool('risky', needsApproval: true));

    $beta = workspace('Beta');
    actingInWorkspace($beta);
    $stepBeta = pausedStep(new FakeTool('risky', needsApproval: true));

    $this->travel(25)->hours();
    app(CurrentWorkspace::class)->set(null);

    $this->artisan('agents:expire-approvals')
        ->expectsOutput('Expired 2 approval(s).')
        ->assertSuccessful();

    actingInWorkspace($this->workspace);
    expect(Run::find($stepAcme->run_id)->status)->toBe(RunStatus::Failed);

    actingInWorkspace($beta);
    expect(Run::find($stepBeta->run_id)->status)->toBe(RunStatus::Failed);
});
