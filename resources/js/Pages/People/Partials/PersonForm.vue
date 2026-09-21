<script setup>
import ButtonLink from '@/Components/ButtonLink.vue';
import CategoryAnswersFieldset from '@/Components/CategoryAnswersFieldset.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PersonPhoto from '@/Components/PersonPhoto.vue';
import PhotoCropper from '@/Components/PhotoCropper.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import FileUpload from 'primevue/fileupload';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    cancelHref: {
        type: String,
        default: null,
    },
    currentPhotoUrl: {
        type: String,
        default: null,
    },
    // The popups each show one half of this form, so neither is overwhelming.
    showCore: {
        type: Boolean,
        default: true,
    },
    showAnswers: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['submit', 'cancel']);

const preview = ref(null);
const cropping = ref(null);
const photoProblem = ref('');
const photoNote = ref('');
const shrinking = ref(false);
const formEl = ref(null);

/*
 * A failed save lands the reader back at the top of the page, where the reason
 * is nowhere in sight — which is how a rejected save comes to look like nothing
 * happening at all. Put the first complaint in front of them instead.
 */
watch(
    () => props.form.errors,
    async (errors) => {
        if (!errors || Object.keys(errors).length === 0) {
            return;
        }

        await nextTick();

        // After Inertia has finished resetting the scroll position.
        setTimeout(() => {
            formEl.value?.querySelector('.p-message')?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }, 150);
    },
    { deep: true },
);

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_PHOTO_BYTES = 25 * 1024 * 1024;

// Phone cameras produce 4-10MB photos. Uploading one over a mobile connection is
// slow and often just fails, so anything larger than this is redrawn smaller
// here before it is ever sent.
const TARGET_LONG_EDGE = 1600;
const WORTH_SHRINKING_BYTES = 600 * 1024;

const megabytes = (bytes) => (bytes / 1024 / 1024).toFixed(1);

/*
 * Redraw a large photo on a canvas at 1600px and re-encode it, which lands
 * around 300-500KB. Anything the browser cannot decode — some HEIC files — is
 * handed back untouched, and the server says so if it will not take it.
 */
const shrink = async (file) => {
    if (file.size <= WORTH_SHRINKING_BYTES || !ALLOWED_TYPES.includes(file.type)) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const longest = Math.max(bitmap.width, bitmap.height);
        const scale = Math.min(1, TARGET_LONG_EDGE / longest);

        if (scale === 1) {
            bitmap.close?.();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        // PNG stays PNG so transparency survives; everything else becomes JPEG.
        const keepPng = file.type === 'image/png';
        const blob = await new Promise((resolve) =>
            canvas.toBlob(resolve, keepPng ? 'image/png' : 'image/jpeg', 0.85),
        );

        if (!blob) {
            return file;
        }

        const stem = file.name.replace(/\.[^.]+$/, '');

        return new File([blob], `${stem}.${keepPng ? 'png' : 'jpg'}`, {
            type: keepPng ? 'image/png' : 'image/jpeg',
        });
    } catch {
        // Undecodable here; let the server have the original.
        return file;
    }
};

const usePhoto = (file, original) => {
    props.form.photo = file;
    props.form.remove_photo = false;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = URL.createObjectURL(file);

    if (original && file.size < original.size) {
        photoNote.value = `Resized from ${megabytes(original.size)} MB to ${megabytes(file.size)} MB.`;
    }
};

/*
 * PrimeVue quietly drops a file it does not like, which looked exactly like the
 * photo not attaching at all. Every rejection here says why, out loud.
 */
const onFileSelect = (event) => {
    const file = event.files?.[0] ?? null;

    photoProblem.value = '';
    photoNote.value = '';
    cropping.value = null;

    if (!file) {
        return;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
        photoProblem.value = 'That is not a photo the browser can read. Use a JPG, PNG or WebP.';

        return;
    }

    if (file.size > MAX_PHOTO_BYTES) {
        photoProblem.value = `That photo is ${megabytes(file.size)} MB. The limit is 25 MB.`;

        return;
    }

    // The square is settled before anything is sent, so the crop is the photo:
    // nothing is thrown away afterwards.
    cropping.value = { url: URL.createObjectURL(file), size: file.size };
};

/*
 * The cropper hands back a square of its own making, which is what goes to the
 * server. It stays open, so the framing can be changed again before saving.
 */
const onCropped = (file, size) => {
    usePhoto(file, cropping.value?.size ?? null);

    photoNote.value = `Square crop, ${size}×${size}. Change the framing any time before saving.`;
};

const closeCropper = () => {
    if (cropping.value?.url) {
        URL.revokeObjectURL(cropping.value.url);
    }

    cropping.value = null;
};

const clearPhoto = () => {
    props.form.photo = null;
    props.form.remove_photo = true;
    preview.value = null;
    photoNote.value = '';
    photoProblem.value = '';
};

/*
 * On a phone the save button is at the bottom and a field error is at the top,
 * which is how a failed save comes to look like nothing happening at all.
 */
const errorSummary = computed(() => {
    const errors = { ...(props.form.errors ?? {}) };

    // The duplicate warning has its own message at the top of the form.
    delete errors.duplicate;

    const messages = Object.values(errors).filter(Boolean);

    if (messages.length === 0) {
        return '';
    }

    return messages.length === 1
        ? String(messages[0])
        : `${messages.length} things need fixing first — ${messages[0]}`;
});

/*
 * The server sends the form back with a warning instead of blocking outright,
 * because two people really can share a name. This is the "yes, I meant it".
 */
const addAnyway = () => {
    props.form.confirm_duplicate = true;
    props.form.clearErrors('duplicate');
    emit('submit');
};
</script>

<template>
    <form ref="formEl" class="space-y-4 sm:space-y-5" @submit.prevent="$emit('submit')">
        <Message v-if="form.errors.duplicate" severity="warn" :closable="false">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span>{{ form.errors.duplicate }}</span>

                <Button
                    type="button"
                    size="small"
                    severity="secondary"
                    outlined
                    label="Add them anyway"
                    class="shrink-0 self-start"
                    @click="addAnyway"
                />
            </div>
        </Message>

        <template v-if="showCore">
            <div>
                <InputLabel for="photo" value="Photo" />

                <PhotoCropper
                    v-if="cropping"
                    :src="cropping.url"
                    @ready="onCropped"
                    @cancel="closeCropper"
                />

                <div v-else class="flex flex-wrap items-center gap-4">
                    <PersonPhoto
                        :src="
                            preview || (currentPhotoUrl && !form.remove_photo ? currentPhotoUrl : null)
                        "
                        :name="form.name"
                        size="lg"
                    />

                    <div class="flex flex-col items-start gap-2">
                        <FileUpload
                            mode="basic"
                            accept="image/png,image/jpeg,image/webp"
                            :auto="true"
                            chooseLabel="Choose photo"
                            @select="onFileSelect"
                        />

                        <Button
                            v-if="currentPhotoUrl && !form.remove_photo"
                            type="button"
                            label="Remove photo"
                            text
                            severity="danger"
                            size="small"
                            @click="clearPhoto"
                        />
                    </div>
                </div>

                <p v-if="photoNote" class="mt-1 text-xs text-gray-500">{{ photoNote }}</p>
                <p v-else class="mt-1 text-xs text-gray-500">
                    JPG, PNG or WebP, up to 25 MB. After picking one you choose the square.
                </p>

                <Message v-if="photoProblem" severity="error" size="small" variant="simple" class="mt-2">
                    {{ photoProblem }}
                </Message>

                <InputError class="mt-2" :message="form.errors.photo" />
            </div>

            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="off"
                    placeholder="First and last name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 sm:gap-5">
                <div>
                    <InputLabel for="phone" value="Phone" />

                    <TextInput
                        id="phone"
                        type="tel"
                        v-model="form.phone"
                        autocomplete="off"
                        placeholder="(555) 555-5555"
                    />

                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        v-model="form.email"
                        autocomplete="off"
                        placeholder="name@example.com"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </div>

            <div>
                <InputLabel for="notes" value="Notes" />

                <Textarea
                    id="notes"
                    v-model="form.notes"
                    rows="5"
                    fluid
                    autoResize
                    placeholder="Plays Wednesday nights, has a net, prefers beach…"
                />

                <p class="mt-1 text-xs text-gray-500">
                    Anything you want to remember before you text them.
                </p>

                <InputError class="mt-2" :message="form.errors.notes" />
            </div>
        </template>

        <CategoryAnswersFieldset
            v-if="showAnswers"
            :categories="categories"
            :answers="form.answers"
        />

        <div class="border-t border-gray-200 pt-4 sm:pt-5">
            <Message v-if="errorSummary" severity="error" size="small" :closable="false" class="mb-4">
                {{ errorSummary }}
            </Message>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <Button
                    v-if="!cancelHref"
                    type="button"
                    severity="secondary"
                    outlined
                    label="Cancel"
                    @click="$emit('cancel')"
                />
                <ButtonLink v-else :href="cancelHref" severity="secondary" outlined label="Cancel" />

                <PrimaryButton :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : submitLabel }}
                </PrimaryButton>
            </div>
        </div>
    </form>
</template>
