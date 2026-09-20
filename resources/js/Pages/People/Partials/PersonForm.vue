<script setup>
import ButtonLink from '@/Components/ButtonLink.vue';
import CategoryAnswersFieldset from '@/Components/CategoryAnswersFieldset.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import FileUpload from 'primevue/fileupload';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';

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

const onFileSelect = (event) => {
    const file = event.files?.[0] ?? null;

    props.form.photo = file;
    props.form.remove_photo = false;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
};

const clearPhoto = () => {
    props.form.photo = null;
    props.form.remove_photo = true;
    preview.value = null;
};

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
    <form class="space-y-5" @submit.prevent="$emit('submit')">
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

                <div class="flex flex-wrap items-center gap-4">
                    <Avatar
                        v-if="preview || (currentPhotoUrl && !form.remove_photo)"
                        :image="preview || currentPhotoUrl"
                        shape="square"
                        size="xlarge"
                    />
                    <Avatar
                        v-else
                        icon="pi pi-user"
                        shape="square"
                        size="xlarge"
                        class="bg-gray-100 text-gray-400"
                    />

                    <div class="flex flex-col items-start gap-2">
                        <FileUpload
                            mode="basic"
                            accept="image/png,image/jpeg,image/webp"
                            :auto="true"
                            :maxFileSize="5000000"
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

                <p class="mt-1 text-xs text-gray-500">JPG, PNG or WebP, up to 5 MB.</p>

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

            <div class="grid gap-5 sm:grid-cols-2">
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
                    rows="6"
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

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-5">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>

            <Button
                v-if="!cancelHref"
                type="button"
                severity="secondary"
                outlined
                label="Cancel"
                @click="$emit('cancel')"
            />
            <ButtonLink v-else :href="cancelHref" severity="secondary" outlined label="Cancel" />
        </div>
    </form>
</template>
