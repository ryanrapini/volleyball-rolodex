<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Message from 'primevue/message';

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

        <h1 class="text-2xl font-semibold text-gray-900">Reset password</h1>

        <p class="mt-2 text-sm text-gray-600">
            Enter your email address and we'll send you a link to choose a new password.
        </p>

        <Message v-if="status" severity="success" :closable="false" class="mt-4">
            {{ status }}
        </Message>

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
