<script setup>
import InputText from 'primevue/inputtext';
import { ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const field = ref(null);

// Breeze's own forms call focus() on a ref to this component after a failed
// submit, so the wrapper has to keep exposing it.
defineExpose({
    focus: () => {
        const el = field.value?.$el ?? field.value;

        el?.focus?.();
    },
});
</script>

<template>
    <InputText ref="field" v-model="model" fluid />
</template>
