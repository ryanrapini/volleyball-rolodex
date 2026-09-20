<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PersonForm from './Partials/PersonForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    phone: '',
    email: '',
    notes: '',
    photo: null,
    remove_photo: false,
});

const submit = () => {
    form.post(route('people.store'));
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
                    submit-label="Add to rolodex"
                    :cancel-href="route('people.index')"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
