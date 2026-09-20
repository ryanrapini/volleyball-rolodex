<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center border-b-4 px-1 pt-1 font-mono text-xs font-semibold uppercase tracking-widest transition-colors duration-100"
        :class="
            active
                ? 'border-riso-pink text-ink'
                : 'border-transparent text-ink/60 hover:border-riso-blue/40 hover:text-ink'
        "
    >
        <slot />
    </Link>
</template>
