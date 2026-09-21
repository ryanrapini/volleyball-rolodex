<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ButtonLink from '@/Components/ButtonLink.vue';
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';

defineProps({
    teams: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Teams" />

    <AuthenticatedLayout>
        <template #actions>
            <ButtonLink :href="route('teams.build')" rounded class="sm:!hidden" aria-label="Build a team">
                <i class="pi pi-bolt" />
            </ButtonLink>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Teams</h1>

                <ButtonLink :href="route('teams.build')">
                    <i class="pi pi-bolt mr-2" />
                    Build a team
                </ButtonLink>
            </div>

            <Card v-if="teams.length === 0">
                <template #content>
                    <div class="py-8 text-center">
                        <p class="text-lg font-semibold text-gray-900">No teams yet.</p>
                        <p class="mt-2 text-sm text-gray-600">
                            Answer a few questions, swipe through who is eligible, and save the
                            people you want to ask.
                        </p>
                        <ButtonLink :href="route('teams.build')" class="mt-5">
                            <i class="pi pi-bolt mr-2" />
                            Build a team
                        </ButtonLink>
                    </div>
                </template>
            </Card>

            <ul v-else class="space-y-2.5">
                <li v-for="team in teams" :key="team.id">
                    <Card :pt="{ body: { class: '!p-3 sm:!p-4' } }">
                        <template #content>
                            <ButtonLink
                                :href="route('teams.show', team.id)"
                                severity="secondary"
                                text
                                class="!block !w-full !p-0 !text-left"
                            >
                                <span class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="min-w-0">
                                        <span class="block text-lg font-semibold tracking-tight text-gray-900">
                                            {{ team.name }}
                                        </span>
                                        <span class="mt-0.5 block text-xs text-gray-500">
                                            {{ team.date_label ?? 'No date yet' }}
                                        </span>
                                    </span>

                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <Tag
                                            :value="`${team.members_count} asked`"
                                            severity="secondary"
                                            class="!border !border-gray-500 !bg-transparent !text-gray-700"
                                        />
                                        <Tag v-if="team.tally.yes" :value="`${team.tally.yes} yes`" severity="success" />
                                        <Tag v-if="team.tally.no" :value="`${team.tally.no} no`" severity="danger" />
                                    </span>
                                </span>
                            </ButtonLink>
                        </template>
                    </Card>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
