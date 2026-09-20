<script setup>
import Avatar from 'primevue/avatar';
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        default: '/',
    },
});
</script>

<template>
    <Link :href="href" class="flex items-center gap-2">
        <Avatar icon="pi pi-users" shape="square" />
        <span class="text-lg font-semibold text-gray-900">Rolodex</span>
    </Link>
</template>
