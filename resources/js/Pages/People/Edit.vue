<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PersonForm from './Partials/PersonForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';

const props = defineProps({
    person: {
        type: Object,
        required: true,
    },
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
    name: props.person.name ?? '',
    phone: props.person.phone ?? '',
    email: props.person.email ?? '',
    notes: props.person.notes ?? '',
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
    form.transform((data) => ({
        ...data,
        answers: Object.entries(data.answers).map(([category_id, answer]) => ({
            category_id,
            ...answer,
        })),
    })).patch(route('people.update', props.person.id));
};
</script>

<template>
    <Head :title="`Edit ${person.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">
                Edit {{ person.name }}
            </h1>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <Card>
                <template #content>
                    <PersonForm
                        :form="form"
                        :categories="categories"
                        submit-label="Save changes"
                        :cancel-href="route('people.show', person.id)"
                        :current-photo-url="person.photo_url"
                        @submit="submit"
                    />
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
