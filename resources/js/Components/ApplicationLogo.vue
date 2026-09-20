<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        default: '/',
    },
});
</script>

<template>
    <Link :href="href" class="inline-flex items-center gap-2">
        <span
            class="flex h-9 w-9 items-center justify-center border-2 border-ink bg-riso-pink text-lg leading-none shadow-print-sm"
            aria-hidden="true"
        >
            🏐
        </span>
        <span class="font-sans text-lg font-bold uppercase tracking-tight text-ink">
            Rolodex
        </span>
    </Link>
</template>
