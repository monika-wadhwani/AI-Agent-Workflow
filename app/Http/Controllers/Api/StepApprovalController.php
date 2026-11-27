<?php

namespace App\Http\Controllers\Api;

use App\Agents\AgentRunner;
use App\Exceptions\ApprovalExpired;
use App\Http\Controllers\Controller;
use App\Http\Resources\RunResource;
use App\Models\RunStep;
use Illuminate\Support\Facades\Gate;
use LogicException;

class StepApprovalController extends Controller
{
    public function approve(string $step, AgentRunner $runner): RunResource
    {
        return $this->decide($step, fn (RunStep $s) => $runner->approve($s));
    }

    public function reject(string $step, AgentRunner $runner): RunResource
    {
        return $this->decide($step, fn (RunStep $s) => $runner->reject($s));
    }

    private function decide(string $id, callable $action): RunResource
    {
        $step = RunStep::findOrFail($id);
        Gate::authorize('decide', $step);

        try {
            $run = $action($step);
        } catch (ApprovalExpired $e) {
            abort(410, $e->getMessage()); // Gone: the window closed
        } catch (LogicException $e) {
            abort(409, $e->getMessage()); // Conflict: already decided
        }

        return new RunResource($run->load('steps'));
    }
}
