<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        class="block w-full px-4 py-2 text-start font-mono text-xs font-semibold uppercase tracking-widest text-ink transition-colors duration-100 hover:bg-riso-pink/20 focus:bg-riso-pink/20 focus:outline-none"
    >
        <slot />
    </Link>
</template>
