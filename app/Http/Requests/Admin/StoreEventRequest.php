<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
            'description' => 'required|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'status' => 'required|in:draft,published,hidden,cancelled',
            'ticket_template_id' => 'nullable|exists:ticket_templates,id',
            'fees_policy_id' => 'nullable|exists:fees_policies,id',
            'currency' => 'nullable|string|max:3',
        ];
    }
}
