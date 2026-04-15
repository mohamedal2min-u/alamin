<?php

namespace App\Http\Requests\FlockNote;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockNoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'note_text'  => ['required', 'string', 'min:1', 'max:10000'],
            'note_type'  => ['nullable', 'string', 'in:general,instruction,operational,alert'],
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'note_text.required' => 'نص الملاحظة مطلوب',
            'note_text.min'      => 'نص الملاحظة لا يمكن أن يكون فارغاً',
            'note_type.in'       => 'نوع الملاحظة غير صحيح',
        ];
    }
}
