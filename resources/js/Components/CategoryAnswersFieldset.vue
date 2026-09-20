<script setup>
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';

/*
 * The answer controls for one person's categories, shared by the person form and
 * the bulk editor so the two can never drift apart.
 *
 * `answers` is keyed by category id and every entry carries all three keys, so
 * only the one matching the category's type is ever filled in:
 *   { value: bool|null, option_id: string|null, option_ids: string[] }
 */
const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    answers: {
        type: Object,
        required: true,
    },
    legend: {
        type: String,
        default: 'Details',
    },
    note: {
        type: String,
        default: null,
    },
});

const yesNoOptions = [
    { label: 'Yes', value: true },
    { label: 'No', value: false },
];

const answer = (categoryId) => props.answers[categoryId];

/*
 * A yes/no question has three states: yes, no, and not recorded. A multi-select
 * button group that allows an empty selection expresses all three, and clicking
 * the active answer again clears it back to unset.
 */
const booleanSelection = (categoryId) => {
    const value = answer(categoryId).value;

    return value === null || value === undefined ? [] : [value];
};

const setBoolean = (categoryId, selection) => {
    const chosen = Array.isArray(selection) ? selection : [];

    answer(categoryId).value = chosen.length ? chosen[chosen.length - 1] : null;
};
</script>

<template>
    <fieldset v-if="categories.length" class="border-t border-gray-200 pt-5">
        <legend class="text-sm font-semibold text-gray-900">{{ legend }}</legend>

        <p v-if="note" class="mt-1 text-xs text-gray-500">{{ note }}</p>

        <div v-for="category in categories" :key="category.id" class="mt-5">
            <p class="mb-1.5 text-sm font-medium text-gray-700">{{ category.name }}</p>

            <SelectButton
                v-if="category.type === 'boolean'"
                :modelValue="booleanSelection(category.id)"
                :options="yesNoOptions"
                optionLabel="label"
                optionValue="value"
                multiple
                allowEmpty
                size="small"
                @update:modelValue="setBoolean(category.id, $event)"
            />

            <Select
                v-else-if="category.type === 'single'"
                v-model="answer(category.id).option_id"
                :options="category.options"
                optionLabel="label"
                optionValue="id"
                placeholder="Not recorded"
                showClear
                fluid
                size="small"
            />

            <SelectButton
                v-else
                v-model="answer(category.id).option_ids"
                :options="category.options"
                optionLabel="label"
                optionValue="id"
                multiple
                allowEmpty
                size="small"
            />
        </div>
    </fieldset>
</template>
