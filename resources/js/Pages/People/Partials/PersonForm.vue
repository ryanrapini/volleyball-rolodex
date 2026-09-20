<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    form: {
        type: Object,
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
</script>

<template>
    <form
        class="space-y-5"
        @submit.prevent="$emit('submit')"
    >
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

        <div class="flex flex-wrap items-center gap-3 border-t-2 border-ink/10 pt-5">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>

            <Link :href="cancelHref" class="btn btn-secondary">Cancel</Link>
        </div>
    </form>
</template>
