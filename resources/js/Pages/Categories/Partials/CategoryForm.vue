<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';
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
            <legend class="label">What kind of answer is it?</legend>

            <div class="grid gap-3 sm:grid-cols-3">
                <label
                    v-for="type in types"
                    :key="type.value"
                    class="cursor-pointer border-2 border-ink p-3 transition-colors duration-100"
                    :class="form.type === type.value ? 'bg-riso-pink/25 shadow-print-sm' : 'bg-white hover:bg-riso-pink/10'"
                >
                    <input
                        type="radio"
                        class="sr-only"
                        :value="type.value"
                        v-model="form.type"
                    />
                    <span class="block font-sans text-sm font-bold uppercase tracking-tight text-ink">
                        {{ type.label }}
                    </span>
                    <span class="mt-1 block font-mono text-[0.7rem] leading-snug text-ink/60">
                        {{ type.hint }}
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
                    <span class="w-6 shrink-0 font-mono text-xs text-ink/40">
                        {{ index + 1 }}
                    </span>

                    <TextInput
                        :id="`option-${index}`"
                        type="text"
                        v-model="form.options[index]"
                        placeholder="Choice"
                    />

                    <button
                        type="button"
                        class="btn btn-secondary shrink-0 px-3"
                        :aria-label="`Remove choice ${index + 1}`"
                        @click="removeOption(index)"
                    >
                        ×
                    </button>
                </div>
            </div>

            <button type="button" class="btn btn-secondary mt-3" @click="addOption">
                Add choice
            </button>

            <InputError class="mt-2" :message="form.errors.options" />
        </div>

        <p v-else class="font-mono text-xs text-ink/60">
            A yes / no category needs no choices — people are simply marked yes or left unset.
        </p>

        <div class="flex flex-wrap items-center gap-3 border-t-2 border-ink/10 pt-5">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>

            <Link :href="cancelHref" class="btn btn-secondary">Cancel</Link>
        </div>
    </form>
</template>
