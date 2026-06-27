<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled at the controller level via ticket ownership
    }

    public function rules(): array
    {
        return [
            'body'        => ['required', 'string', 'min:1', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'A comment body is required.',
            'body.min'      => 'A comment body cannot be empty.',
            'body.max'      => 'A comment must not exceed 10,000 characters.',
        ];
    }

    /**
     * Prepare validated data — normalize is_internal so it's always a bool.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_internal')) {
            $this->merge([
                'is_internal' => filter_var($this->is_internal, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
