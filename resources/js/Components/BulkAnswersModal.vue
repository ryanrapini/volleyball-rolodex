<script setup>
import CategoryAnswersFieldset from '@/Components/CategoryAnswersFieldset.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    personIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'applied']);

const page = usePage();

const categories = ref([]);
const answers = ref({});
const saving = ref(false);
const problem = ref('');

const xsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

// The index page already ships every category and its choices as filter chips,
// so the bulk editor is built from those rather than another round trip.
const buildCategories = () =>
    (page.props.filterOptions ?? []).map((category) => ({
        id: category.id,
        name: category.name,
        type: category.type,
        options: (category.chips ?? []).map((chip) => ({ id: chip.value, label: chip.label })),
    }));

const reset = () => {
    problem.value = '';
    categories.value = buildCategories();
    answers.value = Object.fromEntries(
        categories.value.map((category) => [
            category.id,
            { value: null, option_id: null, option_ids: [] },
        ]),
    );
};

watch(
    () => props.show,
    (open) => {
        if (open) {
            reset();
        }
    },
);

const close = () => {
    emit('close');
};

const submit = async () => {
    saving.value = true;
    problem.value = '';

    const body = new FormData();

    props.personIds.forEach((id, index) => {
        body.append(`people[${index}]`, id);
    });

    // Only what was actually chosen is sent. An untouched category is absent, so
    // the server leaves everyone's existing answer alone.
    let index = 0;

    Object.entries(answers.value).forEach(([categoryId, answer]) => {
        const chosen =
            answer.value !== null ||
            answer.option_id !== null ||
            (answer.option_ids ?? []).length > 0;

        if (!chosen) {
            return;
        }

        body.append(`answers[${index}][category_id]`, categoryId);

        if (answer.value !== null) {
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

    if (index === 0) {
        problem.value = 'Choose at least one answer to apply.';
        saving.value = false;

        return;
    }

    try {
        const response = await fetch(route('people.bulk-answers'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 422) {
            problem.value = Object.values(data.errors ?? {}).flat()[0] ?? 'Some of that is not valid.';

            return;
        }

        if (!response.ok) {
            problem.value = data.message ?? data.error ?? 'Could not apply that.';

            return;
        }

        emit('applied', data);
        router.reload({ only: ['people'] });
        close();
    } catch {
        problem.value = 'Could not apply that.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="max-h-[85vh] overflow-y-auto p-6">
            <h2 class="font-sans text-lg font-bold uppercase tracking-tight text-ink">
                Apply details to {{ personIds.length }}
                {{ personIds.length === 1 ? 'person' : 'people' }}
            </h2>

            <p v-if="problem" class="mt-3 inline-block bg-riso-pink px-2 py-1 font-mono text-xs text-ink">
                {{ problem }}
            </p>

            <div class="mt-4">
                <CategoryAnswersFieldset
                    :categories="categories"
                    :answers="answers"
                    legend="What to set"
                    note="Only the answers you choose are applied. Anything you leave unrecorded keeps whatever that person already has, and pick-any choices are added to what they have rather than replacing it."
                />
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t-2 border-ink/10 pt-5">
                <SecondaryButton @click="close">Cancel</SecondaryButton>
                <PrimaryButton :disabled="saving" @click="submit">
                    {{ saving ? 'Applying…' : 'Apply to all' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
