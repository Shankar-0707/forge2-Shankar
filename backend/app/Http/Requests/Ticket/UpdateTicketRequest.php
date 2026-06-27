<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'string', 'min:3', 'max:255'],
            'description'  => ['sometimes', 'string', 'min:10', 'max:5000'],
            'status'       => ['sometimes', 'in:open,in_progress,on_hold,resolved,closed'],
            'priority'     => ['sometimes', 'in:low,medium,high,urgent'],
            'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            'assigned_to'  => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
