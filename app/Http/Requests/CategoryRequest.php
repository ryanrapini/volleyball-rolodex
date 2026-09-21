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
            // Colours are picked per choice, in the same positions as 'options'.
            // Anything that is not a real hex colour is quietly dropped rather
            // than refused: it is decoration, not data, so the length cap here is
            // only a sanity limit.
            'option_colours' => ['nullable', 'array', 'max:30'],
            'option_colours.*' => ['nullable', 'string', 'max:32'],
            // How this category shows up on a person's card.
            'show_on_card' => ['nullable', 'boolean'],
            'show_name_on_card' => ['nullable', 'boolean'],
            'colour' => ['nullable', 'string', 'max:32'],
            // The filter state the people list opens in. Positions of choices for
            // a choice category, or 'yes' / 'no' for a yes-no one.
            'default_filter' => ['nullable', 'array', 'max:30'],
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

            $this->checkDefaultFilter($validator, $type);
        });
    }

    /**
     * A default filter has to be answerable by this category: yes or no for a
     * yes-no one, and a position that exists for the others.
     */
    private function checkDefaultFilter(Validator $validator, ?CategoryType $type): void
    {
        $submitted = array_map('strval', array_values((array) $this->input('default_filter', [])));

        // "Unset" — nobody has answered — is a legitimate thing to open filtered
        // on, and it is not one of the category's answers, so it drops out here.
        $values = array_values(array_diff($submitted, ['unset']));

        if ($values === []) {
            return;
        }

        if ($type === CategoryType::Boolean) {
            foreach ($values as $value) {
                if (! in_array($value, ['yes', 'no'], true)) {
                    $validator->errors()->add('default_filter', 'A yes / no category can only default to yes or no.');

                    return;
                }
            }

            return;
        }

        $choices = count($this->options());

        if ($type === CategoryType::Single && count($values) > 1) {
            $validator->errors()->add('default_filter', 'A pick-one category can only default to one choice.');

            return;
        }

        foreach ($values as $value) {
            if (! is_numeric($value) || (int) $value < 0 || (int) $value >= $choices) {
                $validator->errors()->add('default_filter', 'That is not a choice in this category.');

                return;
            }
        }
    }

    public function type(): CategoryType
    {
        return CategoryType::from((string) $this->input('type'));
    }

    /**
     * Trimmed, de-duplicated, blank-free choices in the order they were entered,
     * each carrying the colour picked for it. Colours arrive by position in the
     * submitted list, so they are lined up here before blanks are dropped.
     *
     * @return array<int, array{label: string, colour: ?string}>
     */
    public function choices(): array
    {
        $labels = (array) $this->input('options', []);
        $colours = (array) $this->input('option_colours', []);

        $choices = [];
        $seen = [];

        foreach ($labels as $index => $label) {
            $label = trim((string) $label);

            if ($label === '' || in_array($label, $seen, true)) {
                continue;
            }

            $seen[] = $label;

            $choices[] = [
                'label' => $label,
                'colour' => $this->cleanColour($colours[$index] ?? null),
            ];
        }

        return $choices;
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return array_column($this->choices(), 'label');
    }

    /**
     * A hex colour, or nothing at all.
     */
    private function cleanColour(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1
            ? strtolower($value)
            : null;
    }

    /**
     * The tag colour for a yes / no category.
     */
    public function colour(): ?string
    {
        return $this->cleanColour($this->input('colour'));
    }
}
