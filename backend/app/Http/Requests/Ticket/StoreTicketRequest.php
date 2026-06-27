<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by Sanctum middleware + policies
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'min:3', 'max:255'],
            'description'  => ['required', 'string', 'min:10', 'max:5000'],
            'priority'     => ['sometimes', 'in:low,medium,high,urgent'],
            'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            'assigned_to'  => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
