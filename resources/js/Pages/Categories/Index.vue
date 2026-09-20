<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { ref } from 'vue';

defineProps({
    categories: {
        type: Array,
        required: true,
    },
});

const confirming = ref(null);

const form = useForm({});

const askToDelete = (category) => {
    confirming.value = category;
};

const closeModal = () => {
    confirming.value = null;
};

const deleteCategory = () => {
    form.delete(route('categories.destroy', confirming.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Categories</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        How you sort the people in your rolodex
                    </p>
                </div>

                <Button asChild>
                    <Link :href="route('categories.create')">New category</Link>
                </Button>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <Card v-if="categories.length === 0">
                <template #content>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-gray-900">No categories yet.</p>
                        <p class="mt-2 text-sm text-gray-500">
                            Add the things you care about — who can set, who's under six feet, who
                            plays beach.
                        </p>
                        <div class="mt-5">
                            <Button asChild>
                                <Link :href="route('categories.create')">
                                    Add your first category
                                </Link>
                            </Button>
                        </div>
                    </div>
                </template>
            </Card>

            <ul v-else class="space-y-4">
                <li v-for="category in categories" :key="category.id">
                    <Card>
                        <template #content>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="text-base font-semibold text-gray-900">
                                        {{ category.name }}
                                    </h2>
                                    <p class="mt-0.5 text-xs uppercase tracking-widest text-gray-400">
                                        {{ category.type_label }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <Button asChild severity="secondary" outlined size="small">
                                        <Link :href="route('categories.edit', category.id)">
                                            Edit
                                        </Link>
                                    </Button>

                                    <Button
                                        severity="danger"
                                        size="small"
                                        label="Delete"
                                        @click="askToDelete(category)"
                                    />
                                </div>
                            </div>

                            <div v-if="category.has_options" class="mt-3 flex flex-wrap gap-1.5">
                                <Tag
                                    v-for="option in category.options"
                                    :key="option.id"
                                    :value="option.label"
                                    severity="secondary"
                                />
                            </div>
                            <p v-else class="mt-3 text-xs text-gray-500">Yes / no</p>
                        </template>
                    </Card>
                </li>
            </ul>
        </div>

        <Modal :show="confirming !== null" @close="closeModal">
            <div v-if="confirming">
                <h2 class="text-lg font-semibold text-gray-900">
                    Delete “{{ confirming.name }}”?
                </h2>

                <p class="mt-3 text-sm leading-relaxed text-gray-600">
                    This removes the category and every answer recorded against it. There is no
                    undo.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <Button severity="secondary" outlined label="Keep it" @click="closeModal" />

                    <Button
                        severity="danger"
                        label="Yes, delete"
                        :disabled="form.processing"
                        @click="deleteCategory"
                    />
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
