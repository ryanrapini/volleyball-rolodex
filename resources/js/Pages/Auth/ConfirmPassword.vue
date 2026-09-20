<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
            Confirm password
        </h1>

        <p class="mt-2 font-mono text-xs leading-relaxed text-ink/70">
            This is a secure area of the application. Please confirm your password before
            continuing.
        </p>

        <form class="mt-6" @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-6 flex justify-end">
                <PrimaryButton :disabled="form.processing">Confirm</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
