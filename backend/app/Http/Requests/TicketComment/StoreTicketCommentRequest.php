<?php

namespace App\Http\Requests\TicketComment;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'     => ['required', 'string', 'min:1', 'max:3000'],
            'internal' => ['sometimes', 'boolean'],
        ];
    }
}
