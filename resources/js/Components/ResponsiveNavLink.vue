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
        class="block w-full border-s-4 py-2 ps-3 text-start font-mono text-xs font-semibold uppercase tracking-widest transition-colors duration-100"
        :class="
            active
                ? 'border-riso-pink bg-riso-pink/10 text-ink'
                : 'border-transparent text-ink/60 hover:border-riso-blue/40 hover:bg-paper hover:text-ink'
        "
    >
        <slot />
    </Link>
</template>
