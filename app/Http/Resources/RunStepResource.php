<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'tool_name' => $this->tool_name,
            'input' => $this->input,
            'output' => $this->output,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at,
        ];
    }
}