<script setup>
import Button from 'primevue/button';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * Photo picker with a square crop.
 *
 * Every place a person appears shows a square, so a wide photo was being
 * stretched or losing its edges depending on where you looked. Here the square
 * is chosen on purpose: drag to move, slide to zoom, and what is inside the
 * frame is exactly what gets uploaded.
 *
 * The frame is drawn with CSS and rasterised to a real square image only when
 * the crop settles, so dragging stays smooth on a phone.
 */
const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    // The size of the file that comes out. Smaller than the resize threshold, so
    // nothing is thrown away again on the way to the server.
    output: {
        type: Number,
        default: 800,
    },
});

const emit = defineEmits(['ready', 'cancel']);

const FRAME = 288;

const image = ref(null);
const natural = ref({ width: 0, height: 0 });
const zoom = ref(1);
const offset = ref({ x: 0, y: 0 });
const failed = ref(false);

/** The scale that makes the photo fill the frame at all. */
const base = computed(() => {
    if (natural.value.width === 0 || natural.value.height === 0) {
        return 1;
    }

    return Math.max(FRAME / natural.value.width, FRAME / natural.value.height);
});

const drawn = computed(() => ({
    width: natural.value.width * base.value * zoom.value,
    height: natural.value.height * base.value * zoom.value,
}));

/** Keep the photo covering the frame: no gaps, however far it is dragged. */
const clamp = () => {
    const slackX = Math.max(0, (drawn.value.width - FRAME) / 2);
    const slackY = Math.max(0, (drawn.value.height - FRAME) / 2);

    offset.value = {
        x: Math.min(slackX, Math.max(-slackX, offset.value.x)),
        y: Math.min(slackY, Math.max(-slackY, offset.value.y)),
    };
};

const load = () => {
    if (!image.value) {
        return;
    }

    natural.value = { width: image.value.naturalWidth, height: image.value.naturalHeight };
    zoom.value = 1;
    offset.value = { x: 0, y: 0 };

    // Nothing has been dragged yet, and the centre crop still has to be produced:
    // picking a photo and going straight to save must still send one.
    settle();
};

watch(() => props.src, load);
onMounted(load);

/* --- dragging ---------------------------------------------------------- */

let dragging = null;

const startDrag = (event) => {
    dragging = { x: event.clientX, y: event.clientY, moved: false };

    try {
        event.currentTarget.setPointerCapture?.(event.pointerId);
    } catch {
        // Some pointers cannot be captured; dragging still works without it.
    }
};

const onDrag = (event) => {
    if (!dragging) {
        return;
    }

    offset.value = {
        x: offset.value.x + (event.clientX - dragging.x),
        y: offset.value.y + (event.clientY - dragging.y),
    };

    dragging = { x: event.clientX, y: event.clientY, moved: true };
    clamp();
};

const endDrag = () => {
    if (!dragging) {
        return;
    }

    const moved = dragging.moved;
    dragging = null;

    if (moved) {
        settle();
    }
};

const onZoom = () => {
    clamp();
    settle();
};

/*
 * Turn the frame's contents into an actual square image. Offsets are in on-screen
 * pixels, so everything is scaled up to the output size before it is drawn.
 */
let settling = null;

const settle = () => {
    window.clearTimeout(settling);

    settling = window.setTimeout(() => {
        const size = props.output;
        const source = image.value;

        if (!source || natural.value.width === 0) {
            return;
        }

        const ratio = size / FRAME;

        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);

        const width = drawn.value.width * ratio;
        const height = drawn.value.height * ratio;

        context.drawImage(
            source,
            (size - width) / 2 + offset.value.x * ratio,
            (size - height) / 2 + offset.value.y * ratio,
            width,
            height,
        );

        canvas.toBlob(
            (blob) => {
                if (blob) {
                    emit('ready', new File([blob], 'photo.jpg', { type: 'image/jpeg' }), size);
                }
            },
            'image/jpeg',
            0.9,
        );
    }, 140);
};

onBeforeUnmount(() => window.clearTimeout(settling));
</script>

<template>
    <div class="space-y-3">
        <div
            class="relative touch-none overflow-hidden rounded-md bg-gray-900/80"
            :style="{ width: `${FRAME}px`, height: `${FRAME}px` }"
            @pointerdown.prevent="startDrag"
            @pointermove.prevent="onDrag"
            @pointerup="endDrag"
            @pointercancel="endDrag"
            @pointerleave="endDrag"
        >
            <img
                ref="image"
                :src="src"
                alt=""
                draggable="false"
                class="absolute left-1/2 top-1/2 max-w-none cursor-move"
                :style="{
                    width: `${drawn.width}px`,
                    height: `${drawn.height}px`,
                    transform: `translate(calc(-50% + ${offset.x}px), calc(-50% + ${offset.y}px))`,
                }"
                @load="load"
                @error="failed = true"
            />

            <!-- The square that the photo is being cropped to. -->
            <span
                class="pointer-events-none absolute inset-0 rounded-md ring-1 ring-inset ring-white/40"
                aria-hidden="true"
            />
        </div>

        <div class="flex items-center gap-3">
            <i class="pi pi-search-minus text-xs text-gray-400" aria-hidden="true" />

            <input
                v-model.number="zoom"
                type="range"
                min="1"
                max="3"
                step="0.01"
                class="h-6 w-40 accent-gray-900"
                aria-label="Zoom"
                @input="onZoom"
            />

            <i class="pi pi-search-plus text-xs text-gray-400" aria-hidden="true" />
        </div>

        <p class="text-xs text-gray-500">
            Drag the photo to move it, slide to zoom. The square is what everyone sees.
        </p>

        <p v-if="failed" class="text-xs text-red-600">
            That photo could not be opened, so it cannot be cropped. Try another one.
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                label="Centre it again"
                severity="secondary"
                text
                size="small"
                @click="
                    () => {
                        zoom = 1;
                        offset = { x: 0, y: 0 };
                        settle();
                    }
                "
            />

            <Button
                type="button"
                label="Pick another photo"
                severity="secondary"
                text
                size="small"
                @click="$emit('cancel')"
            />
        </div>
    </div>
</template>
