<script setup>
import Button from 'primevue/button';

defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <Button :type="type" severity="danger">
        <slot />
    </Button>
</template>
