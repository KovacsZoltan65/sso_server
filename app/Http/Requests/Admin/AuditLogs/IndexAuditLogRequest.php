<?php

namespace App\Http\Requests\Admin\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:255'],
            'actor_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'severity' => ['nullable', 'string', 'max:50', 'in:info,warning,error'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_field' => ['nullable', 'string', 'in:created_at,event,severity,actor_id,client_id'],
            'sort_order' => ['nullable', 'in:asc,desc,1,-1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
