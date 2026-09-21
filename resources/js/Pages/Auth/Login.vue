<script setup>
import LoginForm from '@/Pages/Auth/Partials/LoginForm.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="text-2xl font-semibold text-gray-900">Log in</h1>

        <div class="mt-6">
            <LoginForm
                id-prefix="login-page"
                :can-reset-password="canResetPassword"
                :status="status"
            />
        </div>

        <p class="mt-6 border-t border-gray-200 pt-4 text-sm text-gray-600">
            No account yet?
            <Link :href="route('register')" class="font-medium text-blue-600 hover:underline">
                Sign up
            </Link>
        </p>
    </GuestLayout>
</template>
