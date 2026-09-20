<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
                    <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                        Categories
                    </h1>
                    <p class="mt-1 font-mono text-xs text-ink/60">
                        How you sort the people in your rolodex
                    </p>
                </div>

                <Link :href="route('categories.create')" class="btn btn-primary">
                    New category
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="categories.length === 0" class="card p-8 text-center shadow-print-sm">
                <p class="font-sans text-lg font-bold text-ink">No categories yet.</p>
                <p class="mt-2 font-mono text-xs text-ink/60">
                    Add the things you care about — who can set, who's under six feet, who plays
                    beach.
                </p>
                <Link :href="route('categories.create')" class="btn btn-primary mt-5">
                    Add your first category
                </Link>
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="category in categories"
                    :key="category.id"
                    class="card p-5 shadow-print-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-sans text-base font-bold text-ink">
                                {{ category.name }}
                            </h2>
                            <p class="mt-0.5 font-mono text-[0.7rem] uppercase tracking-widest text-ink/50">
                                {{ category.type_label }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <Link
                                :href="route('categories.edit', category.id)"
                                class="btn btn-secondary px-3 py-1.5 text-xs"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="btn btn-danger px-3 py-1.5 text-xs"
                                @click="askToDelete(category)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <div v-if="category.has_options" class="mt-3 flex flex-wrap gap-1.5">
                        <span v-for="option in category.options" :key="option.id" class="tag">
                            {{ option.label }}
                        </span>
                    </div>
                    <p v-else class="mt-3 font-mono text-xs text-ink/50">
                        Yes / no
                    </p>
                </li>
            </ul>
        </div>

        <Modal :show="confirming !== null" @close="closeModal">
            <div v-if="confirming" class="p-6">
                <h2 class="font-sans text-lg font-bold uppercase tracking-tight text-ink">
                    Delete “{{ confirming.name }}”?
                </h2>

                <p class="mt-3 font-mono text-xs leading-relaxed text-ink/70">
                    This removes the category and every answer recorded against it. There is no
                    undo.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Keep it</SecondaryButton>

                    <DangerButton :disabled="form.processing" @click="deleteCategory">
                        Yes, delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
