<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
                        class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/60 hover:text-ink"
                    >
                        ← All people
                    </Link>
                    <h1 class="mt-1 font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                        {{ person.name }}
                    </h1>
                </div>

                <Link :href="route('people.edit', person.id)" class="btn btn-primary">
                    Edit
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Photo and contact -->
            <section class="card p-6 shadow-print-sm">
                <div class="flex flex-wrap gap-6">
                    <img
                        v-if="person.photo_url"
                        :src="person.photo_url"
                        :alt="person.name"
                        class="h-40 w-40 shrink-0 border-2 border-ink object-cover shadow-print-sm"
                    />
                    <div
                        v-else
                        class="flex h-40 w-40 shrink-0 items-center justify-center border-2 border-ink bg-riso-pink/20 font-mono text-3xl font-semibold text-ink/50"
                    >
                        {{ initials }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="label">Contact</h2>

                        <dl class="mt-3 space-y-3">
                            <div>
                                <dt class="font-mono text-xs uppercase tracking-widest text-ink/50">
                                    Phone
                                </dt>
                                <dd class="font-sans text-base text-ink">
                                    <a
                                        v-if="person.phone"
                                        :href="`tel:${person.phone}`"
                                        class="link"
                                    >
                                        {{ person.phone }}
                                    </a>
                                    <span v-else class="font-mono text-xs text-ink/40">
                                        Not recorded
                                    </span>
                                </dd>
                            </div>

                            <div>
                                <dt class="font-mono text-xs uppercase tracking-widest text-ink/50">
                                    Email
                                </dt>
                                <dd class="break-all font-sans text-base text-ink">
                                    <a
                                        v-if="person.email"
                                        :href="`mailto:${person.email}`"
                                        class="link"
                                    >
                                        {{ person.email }}
                                    </a>
                                    <span v-else class="font-mono text-xs text-ink/40">
                                        Not recorded
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <!-- Category answers -->
            <section v-if="person.answer_groups.length" class="card p-6 shadow-print-sm">
                <h2 class="label">Details</h2>

                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="group in person.answer_groups"
                        :key="group.name"
                        class="border-l-4 border-riso-blue/30 pl-3"
                    >
                        <dt class="font-mono text-xs uppercase tracking-widest text-ink/50">
                            {{ group.name }}
                        </dt>
                        <dd class="font-sans text-base text-ink">
                            {{ group.answer }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Notes -->
            <section class="card p-6 shadow-print-sm">
                <h2 class="label">Notes</h2>

                <p
                    v-if="person.notes"
                    class="mt-3 whitespace-pre-line font-sans text-base leading-relaxed text-ink"
                >
                    {{ person.notes }}
                </p>
                <p v-else class="mt-3 font-mono text-xs text-ink/40">No notes yet.</p>
            </section>

            <!-- Danger zone -->
            <section class="card p-6 shadow-print-sm">
                <h2 class="label">Delete</h2>
                <p class="mt-2 font-mono text-xs text-ink/60">
                    Removing {{ person.name }} deletes their record and photo for good. This can't
                    be undone.
                </p>

                <DangerButton class="mt-4" @click="confirmDeletion">
                    Delete {{ person.name }}
                </DangerButton>
            </section>
        </div>

        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="font-sans text-lg font-bold uppercase tracking-tight text-ink">
                    Delete {{ person.name }}?
                </h2>

                <p class="mt-3 font-mono text-xs leading-relaxed text-ink/70">
                    This removes them from your rolodex permanently. There is no undo.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Keep them</SecondaryButton>

                    <DangerButton :disabled="form.processing" @click="deletePerson">
                        Yes, delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
