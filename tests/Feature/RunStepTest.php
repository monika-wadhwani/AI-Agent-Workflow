<?php

use App\Enums\StepStatus;
use App\Models\RunStep;

beforeEach(fn () => actingInWorkspace(workspace()));

function stepData(array $overrides = []): array
{
    return array_merge([
        'position' => 1,
        'tool_call_id' => 'call_1',
        'tool_name' => 'http_get',
        'input' => ['url' => 'https://example.com'],
        'status' => StepStatus::Completed,
    ], $overrides);
}

it('stores steps in order under a run', function () {
    $run = newRun(newAgent());

    $run->steps()->create(stepData(['position' => 2, 'tool_call_id' => 'b']));
    $run->steps()->create(stepData(['position' => 1, 'tool_call_id' => 'a']));

    expect($run->steps->pluck('tool_call_id')->all())->toBe(['a', 'b'])
        ->and($run->steps->first()->input)->toBe(['url' => 'https://example.com'])
        ->and($run->steps->first()->status)->toBe(StepStatus::Completed);
});

it('keeps steps private to their workspace', function () {
    $run = newRun(newAgent());
    $step = $run->steps()->create(stepData());

    actingInWorkspace(workspace('Other'));

    expect(RunStep::find($step->id))->toBeNull();
});