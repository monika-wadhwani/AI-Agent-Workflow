<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // public function __construct(private CurrentWorkspace $current) {}
    
    public function handle(Request $request, Closure $next)
    {
        $current = app(\App\Support\CurrentWorkspace::class);
        $current->set(null);

        $slug = $request->header('X-Workspace');
        if (! $slug) {
            abort(400, 'X-Workspace header is required.');
        }

        $workspace = $request->user()?->workspaces()->where('slug', $slug)->first();
        abort_if($workspace === null, 403);

        $current->set($workspace);

        return $next($request);
    }
}
