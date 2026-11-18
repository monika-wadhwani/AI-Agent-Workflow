<?php

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\StepApprovalController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'workspace'])->group(function () {
    Route::get('/workspace', function (Request $request, CurrentWorkspace $current) {
        $workspace = Workspace::findOrFail($current->id());

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'role' => $request->user()->roleIn($workspace)?->value,
        ];
    });
    Route::post('/agents', [AgentController::class, 'store']);
    Route::post('/agents/{agent}/runs', [RunController::class, 'store']);
    Route::get('/runs/{run}', [RunController::class, 'show']);
    Route::post('/steps/{step}/approve', [StepApprovalController::class, 'approve']);
    Route::post('/steps/{step}/reject', [StepApprovalController::class, 'reject']);
});

