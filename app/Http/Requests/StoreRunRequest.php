<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller checks the agent policy
    }

    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'max:5000'],
        ];
    }
}