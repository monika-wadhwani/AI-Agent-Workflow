<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Agent;
use App\Models\Run;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Str;
use App\Agents\AgentRunner;
use App\Agents\ToolRegistry;
use App\Ai\AiResponse;
use App\Ai\FakeAiProvider;
use App\Contracts\AiProvider;
use App\Contracts\Tool;
use App\Enums\WorkspaceRole;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
  ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function workspace(string $name = 'Acme'): Workspace
{
    return Workspace::create([
        'name' => $name,
        'slug' => Str::slug($name). '-'.Str::lower(Str::random(6))
    ]);
}

/** Make this workspace the current tenant. */
function actingInWorkspace(Workspace $workspace): Workspace
{
    app(CurrentWorkspace::class)->set($workspace);

    return $workspace;
}

function newAgent(array $tools = [], int $maxSteps = 8): Agent
{
    return Agent::create([
        'name' => 'Test agent',
        'instructions' => 'Be helpful.',
        'tools' => $tools,
        'max_steps' => $maxSteps,
    ]);
}

function newRun(Agent $agent, string $input = 'Do the thing'): Run
{
    return Run::create([
        'agent_id' => $agent->id,
        'input' => $input
    ]);
}

/** Script the model: it returns these responses in order. */

function fakeAi(AiResponse ...$responses): FakeAiProvider
{
    $fake = new FakeAiProvider($responses);
    app()->instance(AiProvider::class, $fake);

    return $fake;
}

/** Hand a tool to the robot for this test. */
function useTool(Tool $tool): Tool
{
    app(ToolRegistry::class)->register($tool);

    return $tool;
}
function runner(): AgentRunner
{
    return app(AgentRunner::class);
}
function userIn(Workspace $workspace, WorkspaceRole $role = WorkspaceRole::Member): User
{
    $user = User::factory()->create();
    $workspace->members()->attach($user, ['role' => $role->value]);

    return $user;
}