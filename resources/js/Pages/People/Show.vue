<script setup>
import ButtonLink from '@/Components/ButtonLink.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { computed, ref } from 'vue';

const props = defineProps({
    person: {
        type: Object,
        required: true,
    },
});

const confirmingDeletion = ref(false);

const form = useForm({});

const initials = computed(() =>
    props.person.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const confirmDeletion = () => {
    confirmingDeletion.value = true;
};

const closeModal = () => {
    confirmingDeletion.value = false;
};

const deletePerson = () => {
    form.delete(route('people.destroy', props.person.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head :title="person.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <Link
                        :href="route('people.index')"
                        class="text-sm font-medium text-blue-600 hover:underline"
                    >
                        <i class="pi pi-arrow-left mr-1" />
                        All people
                    </Link>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ person.name }}
                    </h1>
                </div>

                <ButtonLink :href="route('people.edit', person.id)">
                    <i class="pi pi-pencil mr-2" />
                    Edit
                </ButtonLink>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Photo and contact -->
            <Card>
                <template #content>
                    <div class="flex flex-wrap gap-6">
                        <Avatar
                            v-if="person.photo_url"
                            :image="person.photo_url"
                            shape="square"
                            size="xlarge"
                            class="h-40 w-40"
                            :pt="{ image: { style: 'object-fit: cover; width: 100%; height: 100%' } }"
                        />
                        <Avatar
                            v-else
                            :label="initials"
                            shape="square"
                            size="xlarge"
                            class="h-40 w-40 bg-gray-100 text-3xl text-gray-500"
                        />

                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Contact
                            </h2>

                            <dl class="mt-3 space-y-3">
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500">
                                        Phone
                                    </dt>
                                    <dd class="text-base text-gray-900">
                                        <a
                                            v-if="person.phone"
                                            :href="`tel:${person.phone}`"
                                            class="text-blue-600 hover:underline"
                                        >
                                            {{ person.phone }}
                                        </a>
                                        <span v-else class="text-sm text-gray-400">
                                            Not recorded
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500">
                                        Email
                                    </dt>
                                    <dd class="break-all text-base text-gray-900">
                                        <a
                                            v-if="person.email"
                                            :href="`mailto:${person.email}`"
                                            class="text-blue-600 hover:underline"
                                        >
                                            {{ person.email }}
                                        </a>
                                        <span v-else class="text-sm text-gray-400">
                                            Not recorded
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Category answers -->
            <Card v-if="person.answer_groups.length">
                <template #content>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Details
                    </h2>

                    <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div v-for="group in person.answer_groups" :key="group.name">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">
                                {{ group.name }}
                            </dt>
                            <dd class="text-base text-gray-900">
                                {{ group.answer }}
                            </dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <!-- Notes -->
            <Card>
                <template #content>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Notes
                    </h2>

                    <p
                        v-if="person.notes"
                        class="mt-3 whitespace-pre-line text-base leading-relaxed text-gray-900"
                    >
                        {{ person.notes }}
                    </p>
                    <p v-else class="mt-3 text-sm text-gray-400">No notes yet.</p>
                </template>
            </Card>

            <!-- Danger zone -->
            <Card>
                <template #content>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Delete
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Removing {{ person.name }} deletes their record and photo for good. This
                        can't be undone.
                    </p>

                    <DangerButton class="mt-4" @click="confirmDeletion">
                        Delete {{ person.name }}
                    </DangerButton>
                </template>
            </Card>
        </div>

        <Modal :show="confirmingDeletion" @close="closeModal">
            <h2 class="text-lg font-semibold text-gray-900">
                Delete {{ person.name }}?
            </h2>

            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                This removes them from your rolodex permanently. There is no undo.
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeModal">Keep them</SecondaryButton>

                <DangerButton :disabled="form.processing" @click="deletePerson">
                    Yes, delete
                </DangerButton>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
