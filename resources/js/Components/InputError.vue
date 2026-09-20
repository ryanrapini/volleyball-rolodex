<script setup>
import Message from 'primevue/message';

defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <Message v-if="message" severity="error" size="small" variant="simple">
        {{ message }}
    </Message>
</template>
