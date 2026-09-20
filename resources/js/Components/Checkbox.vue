<script setup>
import Checkbox from 'primevue/checkbox';
import { computed } from 'vue';

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const emit = defineEmits(['update:checked']);

// A lone checkbox (remember me) is binary; grouped checkboxes carry a value.
const binary = computed(() => props.value === null);
</script>

<template>
    <Checkbox
        :modelValue="checked"
        :value="value"
        :binary="binary"
        @update:modelValue="emit('update:checked', $event)"
    />
</template>
