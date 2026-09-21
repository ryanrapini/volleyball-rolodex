<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PersonForm from './Partials/PersonForm.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    answers: {
        type: Object,
        default: () => ({}),
    },
    suggestedName: {
        type: String,
        default: '',
    },
});

/** A blank answer per category, the shape the answers fieldset works with. */
const blankAnswers = () =>
    Object.fromEntries(
        props.categories.map((category) => [
            category.id,
            { value: null, option_id: null, option_ids: [] },
        ]),
    );

/** Everything a fresh "add another" starts from. */
const emptyForm = () => ({
    name: '',
    phone: '',
    email: '',
    notes: '',
    photo: null,
    remove_photo: false,
    confirm_duplicate: false,
    answers: blankAnswers(),
});

const form = useForm({ ...emptyForm(), name: props.suggestedName });

// Bumped after each save so the photo panel — the cropper, the preview, the file
// picker's own state — starts over rather than keeping the last person's picture.
const formKey = ref(0);

const submit = () => {
    // The server takes answers as a list, so an entry always carries its
    // category id even when every field inside it is empty.
    form.transform((data) => ({
        ...data,
        answers: Object.entries(data.answers).map(([category_id, answer]) => ({
            category_id,
            ...answer,
        })),
    })).post(route('people.store'));
};

/*
 * Adding people arrives in runs, so a save asks whether to carry straight on.
 *
 * Saving does not swap this component for a different one, so Vue keeps the
 * instance and setup never runs a second time: the offer has to react to the
 * flash arriving rather than be read once. The person's id is remembered for the
 * session too, because going back to this page restores the flash it was
 * rendered with.
 */
const page = usePage();
const added = computed(() => page.props.flash?.added ?? null);
const offeredKey = 'rolodex:offered-another';
const asking = ref(false);

watch(
    added,
    (person) => {
        if (!person || window.sessionStorage.getItem(offeredKey) === String(person.id)) {
            return;
        }

        window.sessionStorage.setItem(offeredKey, String(person.id));
        asking.value = true;
    },
    { immediate: true },
);

/** Stay here, everything from the last person wiped, cursor in the name field. */
const addAnother = () => {
    asking.value = false;
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
    formKey.value += 1;

    nextTick(() => {
        const field = document.getElementById('name') ?? document.querySelector('input[type="text"]');

        field?.focus();
    });
};

const viewThem = () => router.visit(route('people.show', added.value.id));

const done = () => router.visit(route('people.index'));
</script>

<template>
    <Head title="Add person" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">Add person</h1>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <Card>
                <template #content>
                    <PersonForm
                        :key="formKey"
                        :form="form"
                        :categories="categories"
                        submit-label="Add to rolodex"
                        :cancel-href="route('people.index')"
                        @submit="submit"
                    />
                </template>
            </Card>
        </div>

        <Modal v-if="added" :show="asking" max-width="md" @close="addAnother">
            <div class="p-6 text-center">
                <!-- A tick that draws itself, so the confirmation is felt as well
                     as read. -->
                <span class="tick-mark mx-auto mb-4 block h-16 w-16">
                    <svg viewBox="0 0 52 52" class="h-full w-full" aria-hidden="true">
                        <circle class="tick-ring" cx="26" cy="26" r="24" fill="none" />
                        <path class="tick-tick" fill="none" d="M14 27.5l7.5 7.5L38 19" />
                    </svg>
                </span>

                <h2 class="text-lg font-semibold text-gray-900">
                    {{ added.name }} Added Successfully
                </h2>

                <p class="mt-2 text-sm text-gray-600">
                    Want to add someone else while you are here?
                </p>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <Button label="Done" severity="secondary" outlined @click="done" />

                    <Button label="Add another" @click="addAnother" />
                </div>

                <button
                    type="button"
                    class="mt-4 text-sm text-gray-500 underline hover:text-gray-700"
                    @click="viewThem"
                >
                    Go to {{ added.name }}
                </button>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.tick-mark {
    animation: tick-pop 420ms cubic-bezier(0.22, 1.2, 0.36, 1) both;
}

.tick-ring {
    stroke: #16a34a;
    stroke-width: 3;
    stroke-dasharray: 151;
    stroke-dashoffset: 151;
    animation: tick-draw 520ms ease-out 60ms forwards;
}

.tick-tick {
    stroke: #16a34a;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 40;
    stroke-dashoffset: 40;
    animation: tick-draw 320ms ease-out 420ms forwards;
}

@keyframes tick-pop {
    from {
        transform: scale(0.5);
        opacity: 0;
    }

    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes tick-draw {
    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tick-mark,
    .tick-ring,
    .tick-tick {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
