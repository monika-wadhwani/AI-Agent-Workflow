<?php

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
});