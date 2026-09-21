<script setup>
import RegisterForm from '@/Pages/Auth/Partials/RegisterForm.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <GuestLayout>
        <Head title="Sign up" />

        <h1 class="text-2xl font-semibold text-gray-900">Sign up</h1>

        <div class="mt-6">
            <RegisterForm id-prefix="register-page" />
        </div>

        <p class="mt-6 border-t border-gray-200 pt-4 text-sm text-gray-600">
            Already registered?
            <Link :href="route('login')" class="font-medium text-blue-600 hover:underline">
                Log in
            </Link>
        </p>
    </GuestLayout>
</template>
