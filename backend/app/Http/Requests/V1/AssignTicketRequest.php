<?php

namespace App\Http\Requests\V1;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = auth()->user()->organization_id;

        return [
            'agent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($organizationId) {
                    $query
                        ->where('organization_id', $organizationId)
                        ->whereIn('role', Role::assignable());
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.exists' => 'The selected agent must belong to your organization and have an agent or admin role.',
        ];
    }
}
