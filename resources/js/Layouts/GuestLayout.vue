<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Card from 'primevue/card';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10">
        <ApplicationLogo href="/" />

        <Card class="mt-8 w-full max-w-md">
            <template #content>
                <slot />
            </template>
        </Card>
    </div>
</template>
