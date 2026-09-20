<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-paper px-4 py-10">
        <ApplicationLogo href="/" />

        <div class="card mt-8 w-full max-w-md px-6 py-6 shadow-print">
            <slot />
        </div>
    </div>
</template>
