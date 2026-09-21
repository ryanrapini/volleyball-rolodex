<script setup>
import { computed, ref } from 'vue';

/*
 * A small palette rather than a bare colour well: tags sit next to each other on
 * a card, and muddy or low-contrast picks look broken. The swatch at the end
 * opens the system picker for anything the palette does not cover.
 */
const palette = [
    '#db2777',
    '#e11d48',
    '#ea580c',
    '#d97706',
    '#16a34a',
    '#0d9488',
    '#0891b2',
    '#2563eb',
    '#7c3aed',
    '#475569',
];

const props = defineProps({
    modelValue: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        default: 'Colour',
    },
});

const emit = defineEmits(['update:modelValue']);

const custom = ref('#475569');

const choose = (colour) => emit('update:modelValue', props.modelValue === colour ? null : colour);

const isCustom = computed(
    () => !!props.modelValue && !palette.includes(props.modelValue.toLowerCase()),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <button
            v-for="colour in palette"
            :key="colour"
            type="button"
            class="h-6 w-6 rounded-full border border-black/10 transition-transform duration-100 hover:scale-110"
            :class="modelValue === colour ? 'ring-2 ring-gray-900 ring-offset-1' : ''"
            :style="{ background: colour }"
            :aria-label="`${label}: ${colour}`"
            :aria-pressed="modelValue === colour"
            @click="choose(colour)"
        />

        <label
            class="relative flex h-6 w-6 cursor-pointer items-center justify-center rounded-full border border-dashed border-gray-400"
            :class="isCustom ? 'ring-2 ring-gray-900 ring-offset-1' : ''"
            :style="isCustom ? { background: modelValue } : {}"
            :title="`${label}: pick another colour`"
        >
            <i
                class="pi pi-pencil text-[9px]"
                :class="isCustom ? 'text-white' : 'text-gray-500'"
                aria-hidden="true"
            />
            <input
                type="color"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                :value="modelValue ?? custom"
                :aria-label="`${label}: custom colour`"
                @input="emit('update:modelValue', $event.target.value.toLowerCase())"
            />
        </label>

        <button
            v-if="modelValue"
            type="button"
            class="ml-1 text-xs text-gray-500 underline hover:text-gray-700"
            @click="emit('update:modelValue', null)"
        >
            Clear
        </button>
    </div>
</template>
