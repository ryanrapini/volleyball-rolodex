<?php

namespace App\Http\Requests;

/**
 * Applying category answers to several people at once from the list.
 *
 * Inherits the whole answer payload contract — the rules, the ownership checks
 * on every category and choice id, and the normalised shape — from the single
 * person form, so the two can never drift apart.
 */
class BulkAnswersRequest extends PersonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'people' => ['required', 'array', 'min:1', 'max:200'],
            'people.*' => ['required', 'uuid'],
            ...$this->answerRules(),
        ];
    }

    /**
     * The people this submission may touch. Anything not in the owner's own
     * rolodex is simply not found, so one user can never write to another's.
     *
     * @return array<int, string>
     */
    public function people(): array
    {
        return array_values(array_map('strval', (array) $this->input('people', [])));
    }
}
