<script setup>
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, useAttrs } from 'vue';

/*
 * A PrimeVue button that is really a link, so navigation stays a real anchor:
 * new tab, middle click and the hover URL all keep working.
 *
 * PrimeVue's asChild does not style the child by itself — it hands the button
 * classes down as slot props, so they have to be spread onto the anchor. Doing
 * that in one place means no call site has to remember it.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    severity: {
        type: String,
        default: null,
    },
    outlined: {
        type: Boolean,
        default: false,
    },
    text: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        default: null,
    },
});

const attrs = useAttrs();

// Only pass the variants that were asked for: a null severity would land on the
// button as a class of its own.
const variants = computed(() => {
    const passed = {};

    if (props.severity) {
        passed.severity = props.severity;
    }

    if (props.size) {
        passed.size = props.size;
    }

    if (props.outlined) {
        passed.outlined = true;
    }

    if (props.text) {
        passed.text = true;
    }

    return passed;
});

const rest = computed(() => {
    const { class: ignored, ...others } = attrs;

    return others;
});
</script>

<template>
    <Button v-slot="{ class: classes, a11yAttrs }" asChild v-bind="variants">
        <Link
            :href="href"
            :class="[classes, attrs.class]"
            v-bind="{ ...rest, ...a11yAttrs }"
        >
            <slot>{{ label }}</slot>
        </Link>
    </Button>
</template>
