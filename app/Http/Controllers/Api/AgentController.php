<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = Agent::create($request->validated());

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }
}