<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="mt-1 inline-block bg-riso-pink px-2 py-1 font-mono text-xs font-semibold text-ink">
            {{ message }}
        </p>
    </div>
</template>
