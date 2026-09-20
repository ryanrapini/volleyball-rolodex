<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
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
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">Log in</h1>

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

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 font-mono text-xs font-semibold uppercase tracking-widest text-ink">
                        Remember me
                    </span>
                </label>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Link v-if="canResetPassword" :href="route('password.request')" class="link text-sm">
                    Forgot your password?
                </Link>
                <span v-else></span>

                <PrimaryButton :disabled="form.processing">Log in</PrimaryButton>
            </div>
        </form>

        <p class="mt-6 border-t-2 border-ink/10 pt-4 font-mono text-xs text-ink/70">
            No account yet?
            <Link :href="route('register')" class="link">Sign up</Link>
        </p>
    </GuestLayout>
</template>
