<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PersonForm from './Partials/PersonForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    answers: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    name: '',
    phone: '',
    email: '',
    notes: '',
    photo: null,
    remove_photo: false,
    answers: Object.fromEntries(
        props.categories.map((category) => [
            category.id,
            {
                value: props.answers[category.id]?.value ?? null,
                option_id: props.answers[category.id]?.option_id ?? null,
                option_ids: [...(props.answers[category.id]?.option_ids ?? [])],
            },
        ]),
    ),
});

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
</script>

<template>
    <Head title="Add person" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                Add person
            </h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="card p-6 shadow-print">
                <PersonForm
                    :form="form"
                    :categories="categories"
                    submit-label="Add to rolodex"
                    :cancel-href="route('people.index')"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
