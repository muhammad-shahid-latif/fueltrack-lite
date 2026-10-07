<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    /**
     * "3120212345671" and "31202 1234567 1" become "31202-1234567-1".
     */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('cnic_number'));

        $this->merge([
            'cnic_number' => strlen($digits) === 13
                ? substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12)
                : ($this->filled('cnic_number') ? trim((string) $this->input('cnic_number')) : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'designation' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'cnic_number' => ['nullable', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('employees', 'cnic_number')->ignore($employee?->id)],
            'joined_on' => ['required', 'date', 'before_or_equal:today'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => $image,
            'cnic_front' => $image,
            'cnic_back' => $image,
            'remove_photo' => ['boolean'],
            'remove_cnic_front' => ['boolean'],
            'remove_cnic_back' => ['boolean'],
        ];
    }

    /**
     * The employee's own fields, without uploads or removal ticks.
     *
     * @return array<string, mixed>
     */
    public function employeeData(): array
    {
        return $this->safe()->only(['name', 'designation', 'phone', 'cnic_number', 'joined_on', 'monthly_salary', 'notes']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'joined_on' => 'joining date',
            'cnic_number' => 'CNIC number',
            'cnic_front' => 'CNIC front',
            'cnic_back' => 'CNIC back',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cnic_number.regex' => 'Enter the 13-digit CNIC as 12345-1234567-1.'];
    }
}
