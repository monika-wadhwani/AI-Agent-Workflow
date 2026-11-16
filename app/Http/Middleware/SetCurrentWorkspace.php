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
    public function __construct(private CurrentWorkspace $current) {}
    
    public function handle(Request $request, Closure $next): Response
    {
       // Start clean: never trust a tenant left over from an earlier request.
       $this->current->set(null);

       $slug = $request->header('X-Workspace');

       if(! is_string($slug) || $slug === ''){
        abort(400, 'The X-Worspace header is required.');
       }

        $workspace = $request->user()?->workspaces()->where('slug', $slug)->first();

        if ($workspace === null) {
            abort(403, 'You do not belong to this workspace.');
        }

        $this->current->set($workspace);
        return $next($request);
    }
}
