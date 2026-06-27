<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `auth:sanctum` route middleware
     * and tenant scoping is applied inside the controller.
     */
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
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:1', 'max:20000'],
            'status' => ['sometimes', 'string', Rule::in([
                'open', 'in_progress', 'pending', 'resolved', 'closed',
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                'low', 'medium', 'high', 'urgent',
            ])],
            // Assignee must belong to the SAME organization as the auth user.
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
        if ($this->has('status') && $this->input('status') === '') {
            $this->request->remove('status');
        }

        if ($this->has('priority') && $this->input('priority') === '') {
            $this->request->remove('priority');
        }
    }
}
