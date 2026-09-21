<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'tournament_date' => ['nullable', 'date'],
            'person_ids' => ['required', 'array', 'min:1', 'max:60'],
            // Only ever from this user's own rolodex.
            'person_ids.*' => [
                'required',
                Rule::exists('people', 'id')->where('user_id', $this->user()->getKey()),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function personIds(): array
    {
        return array_values(array_unique(array_map('strval', (array) $this->input('person_ids', []))));
    }
}
