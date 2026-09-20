<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    types: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    type: 'boolean',
    options: [],
});

const submit = () => {
    form.post(route('categories.store'));
};
</script>

<template>
    <Head title="New category" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                New category
            </h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="card p-6 shadow-print">
                <CategoryForm
                    :form="form"
                    :types="types"
                    submit-label="Add category"
                    :cancel-href="route('categories.index')"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
