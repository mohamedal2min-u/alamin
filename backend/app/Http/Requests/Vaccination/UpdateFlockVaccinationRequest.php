<?php

namespace App\Http\Requests\Vaccination;

use App\Models\FlockVaccination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlockVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(FlockVaccination::STATUSES)],
            'note'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'status.in'       => 'الحالة يجب أن تكون: due أو done أو skipped',
        ];
    }
}
