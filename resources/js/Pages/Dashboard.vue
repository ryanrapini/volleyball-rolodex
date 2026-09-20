<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-sans text-xl font-bold uppercase tracking-tight text-ink">
                Dashboard
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="card p-6 text-ink">
                You're logged in!
            </div>
        </div>
    </AuthenticatedLayout>
</template>
