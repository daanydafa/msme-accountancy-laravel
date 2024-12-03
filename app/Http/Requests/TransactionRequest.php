<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:income,expense',
            'expense_type' => 'required_if:type,expense|string|in:operational,production', 
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'category' => 'required|string',
            'order_id' => 'nullable|string',
            'attachment_url' => 'nullable|url',
            'date' => 'required|date',
        ];
    }
}
