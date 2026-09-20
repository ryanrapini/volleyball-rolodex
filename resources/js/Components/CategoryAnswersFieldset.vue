<script setup>
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

const answer = (categoryId) => props.answers[categoryId];

const setYesNo = (categoryId, value) => {
    const current = answer(categoryId);

    // Clicking the active answer again clears it back to "not recorded".
    current.value = current.value === value ? null : value;
};

const toggleOption = (categoryId, optionId) => {
    const ids = answer(categoryId).option_ids;
    const index = ids.indexOf(optionId);

    if (index === -1) {
        ids.push(optionId);
    } else {
        ids.splice(index, 1);
    }
};
</script>

<template>
    <fieldset v-if="categories.length" class="border-t-2 border-ink/10 pt-5">
        <legend class="label">{{ legend }}</legend>

        <p v-if="note" class="mt-2 font-mono text-xs leading-relaxed text-ink/60">
            {{ note }}
        </p>

        <div
            v-for="category in categories"
            :key="category.id"
            class="mt-4 border-l-4 border-riso-blue/30 pl-3"
        >
            <p class="label">{{ category.name }}</p>

            <!-- Yes / no -->
            <div v-if="category.type === 'boolean'" class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="chip"
                    :class="{ 'chip-active': answer(category.id).value === true }"
                    :aria-pressed="answer(category.id).value === true"
                    @click="setYesNo(category.id, true)"
                >
                    Yes
                </button>
                <button
                    type="button"
                    class="chip"
                    :class="{ 'chip-active': answer(category.id).value === false }"
                    :aria-pressed="answer(category.id).value === false"
                    @click="setYesNo(category.id, false)"
                >
                    No
                </button>
                <span
                    v-if="answer(category.id).value === null"
                    class="font-mono text-xs leading-7 text-ink/40"
                >
                    Not recorded
                </span>
            </div>

            <!-- Pick one -->
            <select
                v-else-if="category.type === 'single'"
                :id="`answer-${category.id}`"
                v-model="answer(category.id).option_id"
                class="input"
            >
                <option :value="null">Not recorded</option>
                <option v-for="option in category.options" :key="option.id" :value="option.id">
                    {{ option.label }}
                </option>
            </select>

            <!-- Pick any -->
            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="option in category.options"
                    :key="option.id"
                    type="button"
                    class="chip"
                    :class="{
                        'chip-active': answer(category.id).option_ids.includes(option.id),
                    }"
                    :aria-pressed="answer(category.id).option_ids.includes(option.id)"
                    @click="toggleOption(category.id, option.id)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>
    </fieldset>
</template>
