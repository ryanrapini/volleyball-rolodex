<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import Message from 'primevue/message';

/*
 * The login form on its own, so the landing page and /login are the same form
 * rather than two that drift apart.
 */
const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    // The landing page shows this and the sign-up form together, so the field
    // ids have to stay unique or their labels point at the wrong input.
    idPrefix: {
        type: String,
        default: 'login',
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

// Both forms on the landing page read the same error bag, so whichever one is
// hidden has to be able to drop errors that are not its own.
defineExpose({ clearErrors: () => form.clearErrors() });
</script>

<template>
    <div>
        <Message v-if="status" severity="success" :closable="false" class="mb-4">
            {{ status }}
        </Message>

        <form @submit.prevent="submit">
            <div>
                <InputLabel :for="`${idPrefix}-email`" value="Email" />

                <TextInput
                    :id="`${idPrefix}-email`"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
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
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-700">Remember me</span>
                </label>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-blue-600 hover:underline"
                >
                    Forgot your password?
                </Link>
                <span v-else></span>

                <PrimaryButton :disabled="form.processing">Log in</PrimaryButton>
            </div>
        </form>
    </div>
</template>
