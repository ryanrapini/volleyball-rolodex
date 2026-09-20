<script setup>
import CategoryAnswersFieldset from '@/Components/CategoryAnswersFieldset.vue';
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
        default: null,
    },
    currentPhotoUrl: {
        type: String,
        default: null,
    },
});

defineEmits(['submit', 'cancel']);

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

        <CategoryAnswersFieldset :categories="categories" :answers="form.answers" />

        <div class="flex flex-wrap items-center gap-3 border-t-2 border-ink/10 pt-5">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>

            <Link v-if="cancelHref" :href="cancelHref" class="btn btn-secondary">Cancel</Link>
            <button v-else type="button" class="btn btn-secondary" @click="$emit('cancel')">
                Cancel
            </button>
        </div>
    </form>
</template>
