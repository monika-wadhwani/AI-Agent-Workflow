<?php

namespace App\Http\Requests;

use App\Agents\ToolRegistry;
use App\Models\Agent;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Agent::class, app(CurrentWorkspace::class)->workspace()]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'tools' => ['present', 'array'],
            'tools.*' => ['string', Rule::in(app(ToolRegistry::class)->names())],
            'max_steps' => ['sometimes', 'integer', 'between:1,20'],
        ];
    }
}