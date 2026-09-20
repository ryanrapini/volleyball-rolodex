<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    types: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: props.category.name,
    type: props.category.type,
    options: props.category.options.map((option) => option.label),
});

const submit = () => {
    form.patch(route('categories.update', props.category.id));
};
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                Edit {{ category.name }}
            </h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="card p-6 shadow-print">
                <CategoryForm
                    :form="form"
                    :types="types"
                    submit-label="Save category"
                    :cancel-href="route('categories.index')"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
