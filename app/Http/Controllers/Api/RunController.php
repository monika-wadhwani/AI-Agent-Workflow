<?php

namespace App\Http\Controllers\Api;

use App\Agents\AgentRunner;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Models\Agent;
use App\Models\Run;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RunController extends Controller
{
    public function store(StoreRunRequest $request, string $agent, AgentRunner $runner): JsonResponse
    {
        $agent = Agent::findOrFail($agent);
        Gate::authorize('start', $agent);

        $run = Run::create([
            'agent_id' => $agent->id,
            'input' => $request->validated('input'),
        ]);

        $run = $runner->start($run);

        return (new RunResource($run->load('steps')))->response()->setStatusCode(201);
    }

    public function show(string $run): RunResource
    {
        $run = Run::with('steps')->findOrFail($run);
        Gate::authorize('view', $run);

        return new RunResource($run);
    }
}