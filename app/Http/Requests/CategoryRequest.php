<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('categories', 'name')
                    ->where('user_id', $this->user()->getKey())
                    ->ignore($this->route('category')),
            ],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'options' => ['array', 'max:30'],
            'options.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * A pick-one or pick-any category with no choices is unusable.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = CategoryType::tryFrom((string) $this->input('type'));

            if ($type?->hasOptions() && $this->options() === []) {
                $validator->errors()->add('options', 'Add at least one choice for this category.');
            }
        });
    }

    public function type(): CategoryType
    {
        return CategoryType::from((string) $this->input('type'));
    }

    /**
     * Trimmed, de-duplicated, blank-free choices in the order they were entered.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        $labels = array_map(
            fn (mixed $label): string => trim((string) $label),
            (array) $this->input('options', []),
        );

        return array_values(array_unique(array_filter($labels, fn (string $label): bool => $label !== '')));
    }
}
