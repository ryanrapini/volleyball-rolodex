<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';

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
    option_colours: [],
    default_filter: [],
    show_on_card: true,
    show_name_on_card: true,
    colour: null,
});

const submit = () => {
    form.post(route('categories.store'));
};
</script>

<template>
    <Head title="New category" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">New category</h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <Card>
                <template #content>
                    <CategoryForm
                        :form="form"
                        :types="types"
                        submit-label="Add category"
                        :cancel-href="route('categories.index')"
                        @submit="submit"
                    />
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
