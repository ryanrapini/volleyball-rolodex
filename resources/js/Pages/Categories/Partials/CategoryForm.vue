<script setup>
import ButtonLink from '@/Components/ButtonLink.vue';
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
    },
);

const addOption = () => props.form.options.push('');

const removeOption = (index) => props.form.options.splice(index, 1);
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

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-5">
            <Button type="submit" :label="submitLabel" :disabled="form.processing" />

            <ButtonLink :href="cancelHref" severity="secondary" outlined label="Cancel" />
        </div>
    </form>
</template>
