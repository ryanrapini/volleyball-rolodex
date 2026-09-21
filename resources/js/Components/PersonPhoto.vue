<script setup>
import { computed } from 'vue';

/*
 * One photo box, used everywhere a person is shown: the list, the form and the
 * detail page. It was three slightly different Avatars before, which is how the
 * same picture came out stretched in one place and right in another.
 *
 * `object-cover` keeps the aspect ratio, and the box clips the image so the
 * rounded corners match the initials version exactly.
 */
const props = defineProps({
    src: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
    },
});

const sizes = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-12 w-12 text-base',
    lg: 'h-20 w-20 text-2xl',
    xl: 'h-28 w-28 text-3xl',
};

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <div
        class="flex shrink-0 select-none items-center justify-center overflow-hidden rounded-md bg-gray-100 font-medium text-gray-500"
        :class="sizes[size] ?? sizes.md"
    >
        <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover" />

        <span v-else>{{ initials }}</span>
    </div>
</template>
