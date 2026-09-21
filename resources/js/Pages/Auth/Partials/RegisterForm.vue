<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

/*
 * The sign-up form on its own, shared by the landing page and /register.
 */
defineProps({
    idPrefix: {
        type: String,
        default: 'register',
    },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

defineExpose({ clearErrors: () => form.clearErrors() });
</script>

<template>
    <div>
        <form @submit.prevent="submit">
            <div>
                <InputLabel :for="`${idPrefix}-name`" value="Name" />

                <TextInput
                    :id="`${idPrefix}-name`"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel :for="`${idPrefix}-email`" value="Email" />

                <TextInput
                    :id="`${idPrefix}-email`"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel :for="`${idPrefix}-password`" value="Password" />

                <TextInput
                    :id="`${idPrefix}-password`"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel :for="`${idPrefix}-password-confirmation`" value="Confirm password" />

                <TextInput
                    :id="`${idPrefix}-password-confirmation`"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-6 flex justify-end">
                <PrimaryButton :disabled="form.processing">Sign up</PrimaryButton>
            </div>
        </form>
    </div>
</template>
