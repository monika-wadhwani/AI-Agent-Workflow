<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'status' => $this->status->value,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'steps' => RunStepResource::collection($this->whenLoaded('steps')),
            'created_at' => $this->created_at,
        ];
    }
}