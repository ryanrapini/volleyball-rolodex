<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PersonForm from './Partials/PersonForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    person: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.person.name ?? '',
    phone: props.person.phone ?? '',
    email: props.person.email ?? '',
    notes: props.person.notes ?? '',
});

const submit = () => {
    form.patch(route('people.update', props.person.id));
};
</script>

<template>
    <Head :title="`Edit ${person.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                Edit {{ person.name }}
            </h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="card p-6 shadow-print">
                <PersonForm
                    :form="form"
                    submit-label="Save changes"
                    :cancel-href="route('people.show', person.id)"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
