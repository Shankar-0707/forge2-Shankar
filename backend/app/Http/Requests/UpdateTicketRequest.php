<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'subject' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'min:1', 'max:20000'],
            'status' => ['sometimes', 'string', Rule::in([
                'open', 'in_progress', 'pending', 'resolved', 'closed',
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                'low', 'medium', 'high', 'urgent',
            ])],
            'assignee_id' => [
                'sometimes',
                'string',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'priority'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->request->remove($field);
            }
        }
    }
}
