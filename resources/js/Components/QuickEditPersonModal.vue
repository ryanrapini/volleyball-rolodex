<script setup>
import Modal from '@/Components/Modal.vue';
import PersonForm from '@/Pages/People/Partials/PersonForm.vue';
import { useForm } from '@inertiajs/vue3';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import { computed, ref, watch } from 'vue';

/*
 * One popup, two jobs. `mode` decides which half of the form it shows:
 *   'details' — photo, name, phone, email, notes
 *   'tags'    — the category answers only
 */
const props = defineProps({
    personId: {
        type: String,
        default: null,
    },
    mode: {
        type: String,
        default: 'details',
    },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const problem = ref('');
const categories = ref([]);
const currentPhotoUrl = ref(null);

const xsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const form = useForm({
    name: '',
    phone: '',
    email: '',
    notes: '',
    photo: null,
    remove_photo: false,
    answers: {},
});

const isTags = computed(() => props.mode === 'tags');

const heading = computed(() => {
    if (!form.name) {
        return isTags.value ? 'Categories' : 'Edit person';
    }

    return isTags.value ? `Categories for ${form.name}` : `Edit ${form.name}`;
});

const submitLabel = computed(() => (isTags.value ? 'Save categories' : 'Save details'));

// Load on open, so the popup always starts from what is actually stored. The
// list card only carries an excerpt of the notes.
watch(
    () => [props.personId, props.mode],
    async ([id]) => {
        if (!id) {
            return;
        }

        loading.value = true;
        problem.value = '';
        form.clearErrors();

        try {
            const response = await fetch(route('people.quick-edit', id), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                problem.value = 'Could not load that person.';
                categories.value = [];

                return;
            }

            const data = await response.json();

            categories.value = data.categories ?? [];
            currentPhotoUrl.value = data.person.photo_url ?? null;

            form.name = data.person.name ?? '';
            form.phone = data.person.phone ?? '';
            form.email = data.person.email ?? '';
            form.notes = data.person.notes ?? '';
            form.photo = null;
            form.remove_photo = false;

            // Every category gets an entry carrying all three keys, so the
            // controls never read off the end of a missing answer.
            form.answers = Object.fromEntries(
                categories.value.map((category) => [
                    category.id,
                    {
                        value: data.answers?.[category.id]?.value ?? null,
                        option_id: data.answers?.[category.id]?.option_id ?? null,
                        option_ids: [...(data.answers?.[category.id]?.option_ids ?? [])],
                    },
                ]),
            );
        } catch {
            problem.value = 'Could not load that person.';
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

/*
 * This endpoint answers with plain JSON rather than an Inertia response, so the
 * save goes out as a raw fetch. Handing it to Inertia means the request lands
 * and the row changes, but the client never hears about it: no success, no
 * close, no refresh.
 */
const submit = async () => {
    const body = new FormData();

    // The name is always sent: the endpoint requires it, and sending it
    // unchanged keeps this a no-op for the fields this popup does not show.
    body.append('name', form.name ?? '');

    if (!isTags.value) {
        body.append('phone', form.phone ?? '');
        body.append('email', form.email ?? '');
        body.append('notes', form.notes ?? '');
        body.append('remove_photo', form.remove_photo ? '1' : '0');

        if (form.photo) {
            body.append('photo', form.photo);
        }
    }

    // Answers go out as a list, so an entry always carries its category id even
    // when every field inside it is empty. An unset value is simply absent.
    let index = 0;

    Object.entries(form.answers).forEach(([categoryId, answer]) => {
        body.append(`answers[${index}][category_id]`, categoryId);

        if (answer.value !== null && answer.value !== undefined) {
            body.append(`answers[${index}][value]`, answer.value ? '1' : '0');
        }

        if (answer.option_id) {
            body.append(`answers[${index}][option_id]`, answer.option_id);
        }

        (answer.option_ids ?? []).forEach((optionId, position) => {
            body.append(`answers[${index}][option_ids][${position}]`, optionId);
        });

        index += 1;
    });

    form.processing = true;
    form.clearErrors();
    problem.value = '';

    try {
        const response = await fetch(route('people.quick-update', props.personId), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 422) {
            form.setError(data.errors ?? {});
            problem.value = 'Some of that needs fixing.';

            return;
        }

        if (!response.ok) {
            problem.value = data.message ?? data.error ?? 'Could not save.';

            return;
        }

        emit('saved', data);
        emit('close');
    } catch {
        problem.value = 'Could not save.';
    } finally {
        form.processing = false;
    }
};
</script>

<template>
    <Modal :show="!!personId" max-width="lg" @close="$emit('close')">
        <h2 class="text-lg font-semibold text-gray-900">{{ heading }}</h2>

        <Message v-if="problem" severity="error" size="small" variant="simple" class="mt-3">
            {{ problem }}
        </Message>

        <Skeleton v-if="loading" height="12rem" class="mt-5" />

        <div v-else class="mt-5">
            <PersonForm
                :form="form"
                :categories="categories"
                :current-photo-url="currentPhotoUrl"
                :show-core="!isTags"
                :show-answers="isTags"
                :submit-label="submitLabel"
                @submit="submit"
                @cancel="$emit('close')"
            />
        </div>
    </Modal>
</template>
