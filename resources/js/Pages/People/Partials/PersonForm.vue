<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    cancelHref: {
        type: String,
        required: true,
    },
    currentPhotoUrl: {
        type: String,
        default: null,
    },
});

defineEmits(['submit']);

const fileInput = ref(null);
const preview = ref(null);

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;

    props.form.photo = file;
    props.form.remove_photo = false;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
};

const clearPhoto = () => {
    props.form.photo = null;
    props.form.remove_photo = true;
    preview.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const answer = (categoryId) => props.form.answers[categoryId];

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
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="photo" value="Photo" />

            <div class="flex flex-wrap items-center gap-4">
                <img
                    v-if="preview || (currentPhotoUrl && !form.remove_photo)"
                    :src="preview || currentPhotoUrl"
                    alt=""
                    class="h-20 w-20 border-2 border-ink object-cover shadow-print-sm"
                />
                <div
                    v-else
                    class="flex h-20 w-20 items-center justify-center border-2 border-ink bg-riso-pink/20 font-mono text-xs text-ink/50"
                >
                    No photo
                </div>

                <div class="space-y-2">
                    <input
                        id="photo"
                        ref="fileInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="input file:mr-3 file:border-2 file:border-ink file:bg-riso-pink file:px-3 file:py-1 file:font-mono file:text-xs file:font-semibold file:uppercase file:text-ink"
                        @change="onFileChange"
                    />

                    <button
                        v-if="currentPhotoUrl && !form.remove_photo"
                        type="button"
                        class="link text-xs"
                        @click="clearPhoto"
                    >
                        Remove this photo
                    </button>
                </div>
            </div>

            <p class="help">JPG, PNG or WebP, up to 5 MB.</p>

            <InputError class="mt-2" :message="form.errors.photo" />
        </div>

        <div>
            <InputLabel for="name" value="Name" />

            <TextInput
                id="name"
                type="text"
                v-model="form.name"
                required
                autofocus
                autocomplete="off"
                placeholder="First and last name"
            />

            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <InputLabel for="phone" value="Phone" />

                <TextInput
                    id="phone"
                    type="tel"
                    v-model="form.phone"
                    autocomplete="off"
                    placeholder="(555) 555-5555"
                />

                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    autocomplete="off"
                    placeholder="name@example.com"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>
        </div>

        <div>
            <InputLabel for="notes" value="Notes" />

            <textarea
                id="notes"
                v-model="form.notes"
                rows="6"
                class="input"
                placeholder="Plays Wednesday nights, has a net, prefers beach…"
            ></textarea>

            <p class="help">Anything you want to remember before you text them.</p>

            <InputError class="mt-2" :message="form.errors.notes" />
        </div>

        <!-- Category answers -->
        <fieldset v-if="categories.length" class="border-t-2 border-ink/10 pt-5">
            <legend class="label">Details</legend>

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
                    <option
                        v-for="option in category.options"
                        :key="option.id"
                        :value="option.id"
                    >
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

        <div class="flex flex-wrap items-center gap-3 border-t-2 border-ink/10 pt-5">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>

            <Link :href="cancelHref" class="btn btn-secondary">Cancel</Link>
        </div>
    </form>
</template>
