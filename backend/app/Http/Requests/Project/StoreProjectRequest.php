<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Sanctum + controller org-scoping
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<int,string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:active,archived,on_hold'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after:today'],
        ];
    }

    /**
     * Ensure organization_id and created_by are never accepted from request input.
     */
    protected function prepareForValidation(): void
    {
        $this->offsetUnset('organization_id');
        $this->offsetUnset('created_by');
        $this->offsetUnset('id');
    }
}
