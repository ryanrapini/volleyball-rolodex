<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
            Reset password
        </h1>

        <p class="mt-2 font-mono text-xs leading-relaxed text-ink/70">
            Enter your email address and we'll send you a link to choose a new password.
        </p>

        <div
            v-if="status"
            class="mt-4 inline-block bg-riso-pink/25 px-2 py-1 font-mono text-xs font-semibold text-ink"
        >
            {{ status }}
        </div>

        <form class="mt-6" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6 flex items-center justify-end">
                <PrimaryButton :disabled="form.processing">
                    Email reset link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
