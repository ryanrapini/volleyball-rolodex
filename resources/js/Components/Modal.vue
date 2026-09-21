<script setup>
import Dialog from 'primevue/dialog';

/*
 * Thin wrapper over PrimeVue's Dialog, kept so every existing caller can go on
 * using `:show` and `@close`.
 */
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const widths = {
    sm: '26rem',
    md: '32rem',
    lg: '40rem',
    xl: '48rem',
    '2xl': '56rem',
};
</script>

<template>
    <Dialog
        :visible="show"
        :style="{ width: widths[maxWidth] ?? widths['2xl'] }"
        :closable="closeable"
        :closeOnEscape="closeable"
        :dismissableMask="closeable"
        modal
        @update:visible="closeable && emit('close')"
    >
        <slot />
    </Dialog>
</template>

<style scoped>
/*
 * With no title in the header the close button is the only child, and the
 * header's space-between drops it at the left edge. A thumb reaches the right.
 */
:deep(.p-dialog-header > .p-dialog-close-button) {
    margin-left: auto;
}
</style>
