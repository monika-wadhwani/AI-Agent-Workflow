<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Jobs\ExecuteRun;
use App\Models\Agent;
use App\Models\Run;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RunController extends Controller
{
    public function store(StoreRunRequest $request, string $agent): JsonResponse
    {
        $agent = Agent::findOrFail($agent);
        Gate::authorize('start', $agent);

        $run = Run::create([
            'agent_id' => $agent->id,
            'input' => $request->validated('input'),
        ]);

        ExecuteRun::dispatch($run->id, $run->workspace_id);

        // 202 Accepted: the work is queued, the client checks GET /api/runs/{id} for progress.
        return (new RunResource($run->refresh()))->response()->setStatusCode(202);
    }

    public function show(string $run): RunResource
    {
        $run = Run::with('steps')->findOrFail($run);
        Gate::authorize('view', $run);

        return new RunResource($run);
    }
}