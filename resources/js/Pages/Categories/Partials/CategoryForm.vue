<script setup>
import ButtonLink from '@/Components/ButtonLink.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ColourSwatches from '@/Components/ColourSwatches.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import { computed, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    types: {
        type: Array,
        required: true,
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    cancelHref: {
        type: String,
        required: true,
    },
});

defineEmits(['submit']);

const selected = computed(() => props.types.find((type) => type.value === props.form.type));
const needsOptions = computed(() => selected.value?.has_options === true);

// Switching from yes/no to a choice category needs somewhere to type.
watch(
    () => props.form.type,
    () => {
        if (needsOptions.value && props.form.options.length === 0) {
            props.form.options.push('');
        }

        // A different kind of answer means the old default no longer applies.
        props.form.default_filter = [];
    },
);

const addOption = () => props.form.options.push('');

const removeOption = (index) => props.form.options.splice(index, 1);

/*
 * Default filter values are choice positions here, not ids: a category that does
 * not exist yet has none, and the server turns positions into ids when it saves.
 */
const isDefault = (value) =>
    (props.form.default_filter ?? []).some((entry) => String(entry) === String(value));

const toggleDefault = (value) => {
    if (props.form.type === 'single') {
        props.form.default_filter = isDefault(value) ? [] : [value];

        return;
    }

    const chosen = [...(props.form.default_filter ?? [])];
    const at = chosen.findIndex((entry) => String(entry) === String(value));

    if (at === -1) {
        chosen.push(value);
    } else {
        chosen.splice(at, 1);
    }

    props.form.default_filter = chosen;
};

/*
 * Colours are kept beside the choices, in the same order, so a choice that has
 * no colour yet still leaves the later ones lined up.
 */
const setOptionColour = (index, colour) => {
    const colours = [...(props.form.option_colours ?? [])];

    while (colours.length <= index) {
        colours.push(null);
    }

    colours[index] = colour;

    props.form.option_colours = colours;
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Category name" />

            <TextInput
                id="name"
                type="text"
                v-model="form.name"
                required
                autofocus
                autocomplete="off"
                placeholder="Can set, Under 6 ft, Skill level…"
            />

            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <fieldset>
            <legend class="mb-1 text-sm font-medium text-gray-700">
                What kind of answer is it?
            </legend>

            <div class="grid gap-3 sm:grid-cols-3">
                <label
                    v-for="type in types"
                    :key="type.value"
                    class="flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors duration-100"
                    :class="
                        form.type === type.value
                            ? 'border-gray-900 bg-gray-100'
                            : 'border-gray-200 hover:bg-gray-50'
                    "
                >
                    <RadioButton v-model="form.type" :value="type.value" />

                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">
                            {{ type.label }}
                        </span>
                        <span class="mt-1 block text-xs leading-snug text-gray-500">
                            {{ type.hint }}
                        </span>
                    </span>
                </label>
            </div>

            <InputError class="mt-2" :message="form.errors.type" />
        </fieldset>

        <div v-if="needsOptions">
            <InputLabel value="Choices" />

            <div class="space-y-2">
                <div
                    v-for="(option, index) in form.options"
                    :key="index"
                    class="flex items-center gap-2"
                >
                    <span class="w-6 shrink-0 text-xs text-gray-400">
                        {{ index + 1 }}
                    </span>

                    <TextInput
                        :id="`option-${index}`"
                        type="text"
                        v-model="form.options[index]"
                        placeholder="Choice"
                    />

                    <Button
                        type="button"
                        icon="pi pi-times"
                        text
                        severity="secondary"
                        class="shrink-0"
                        :aria-label="`Remove choice ${index + 1}`"
                        @click="removeOption(index)"
                    />
                </div>
            </div>

            <Button
                type="button"
                icon="pi pi-plus"
                label="Add choice"
                severity="secondary"
                outlined
                class="mt-3"
                @click="addOption"
            />

            <InputError class="mt-2" :message="form.errors.options" />
        </div>

        <p v-else class="text-xs text-gray-500">
            A yes / no category needs no choices — people are simply marked yes or left unset.
        </p>

        <div>
            <InputLabel value="Default filter" />

            <p class="mt-1 text-xs text-gray-500">
                The people list opens with these filters already applied. Tick nothing for no
                default, and you can always change them on the list itself.
            </p>

            <div v-if="!needsOptions" class="mt-3 flex flex-wrap gap-5">
                <label
                    v-for="choice in [
                        { label: 'Yes', value: 'yes' },
                        { label: 'No', value: 'no' },
                    ]"
                    :key="choice.value"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :checked="isDefault(choice.value)"
                        @update:checked="toggleDefault(choice.value)"
                    />
                    <span class="text-sm text-gray-700">{{ choice.label }}</span>
                </label>
            </div>

            <p v-else-if="form.options.length === 0" class="mt-3 text-xs text-gray-500">
                Add choices first, then pick which of them the list should start with.
            </p>

            <div v-else class="mt-3 space-y-2">
                <label
                    v-for="(option, index) in form.options"
                    :key="index"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :checked="isDefault(index)"
                        @update:checked="toggleDefault(index)"
                    />
                    <span class="text-sm text-gray-700">{{ option || `Choice ${index + 1}` }}</span>
                </label>
            </div>

            <InputError class="mt-2" :message="form.errors.default_filter" />
        </div>

        <div class="space-y-4 border-t border-gray-200 pt-5">
            <div>
                <InputLabel value="On the person card" />

                <p class="mt-1 text-xs text-gray-500">
                    What shows on this person's card in the list. Unanswered categories never show
                    anyway.
                </p>
            </div>

            <label class="flex items-start gap-3">
                <Checkbox :checked="form.show_on_card" @update:checked="form.show_on_card = $event" />

                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-700">Show this category</span>
                    <span class="mt-0.5 block text-xs leading-snug text-gray-500">
                        Turn it off to keep this category out of the list view.
                    </span>
                </span>
            </label>

            <label v-if="needsOptions" class="flex items-start gap-3">
                <Checkbox
                    :checked="form.show_name_on_card"
                    @update:checked="form.show_name_on_card = $event"
                />

                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-700">
                        Show the category name too
                    </span>
                    <span class="mt-0.5 block text-xs leading-snug text-gray-500">
                        Off shows just the answer — "Sand" rather than "Plays on: Sand".
                    </span>
                </span>
            </label>

            <div v-if="!needsOptions">
                <InputLabel value="Tag colour" />

                <div class="mt-2">
                    <ColourSwatches v-model="form.colour" label="Tag colour" />
                </div>
            </div>

            <div v-else-if="form.options.some((option) => (option ?? '').trim() !== '')">
                <InputLabel value="Tag colours" />

                <p class="mt-1 text-xs text-gray-500">
                    Give the answers that matter a colour of their own.
                </p>

                <div class="mt-3 space-y-2.5">
                    <div
                        v-for="(option, index) in form.options"
                        :key="index"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <span class="w-28 shrink-0 truncate text-sm text-gray-700">
                            {{ option || `Choice ${index + 1}` }}
                        </span>

                        <ColourSwatches
                            :model-value="form.option_colours[index] ?? null"
                            :label="`Colour for ${option || `choice ${index + 1}`}`"
                            @update:model-value="setOptionColour(index, $event)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-5">
            <Button type="submit" :label="submitLabel" :disabled="form.processing" />

            <ButtonLink :href="cancelHref" severity="secondary" outlined label="Cancel" />
        </div>
    </form>
</template>
