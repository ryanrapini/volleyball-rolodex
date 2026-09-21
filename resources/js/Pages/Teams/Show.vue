<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ButtonLink from '@/Components/ButtonLink.vue';
import Modal from '@/Components/Modal.vue';
import PersonPhoto from '@/Components/PersonPhoto.vue';
import { xsrfToken } from '@/csrf';
import { tagStyle } from '@/tagColour';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

/*
 * The team itself: everyone who was kept, so they can be asked. Each one is marked
 * as they answer — a yes puts a tick by the name, a no greys them out but leaves
 * them on the list, because they were still asked.
 */
const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    members: {
        type: Array,
        default: () => [],
    },
    tally: {
        type: Object,
        default: () => ({ waiting: 0, yes: 0, no: 0, total: 0 }),
    },
});

const rows = ref(props.members.map((member) => ({ ...member })));
const tally = ref({ ...props.tally });
const busy = ref(null);
const confirming = ref(false);

const said = computed(() => ({
    yes: rows.value.filter((row) => row.response === 'yes').length,
    no: rows.value.filter((row) => row.response === 'no').length,
    waiting: rows.value.filter((row) => row.response === 'waiting').length,
}));

const mark = async (row, response) => {
    if (row.response === response || busy.value !== null) {
        return;
    }

    busy.value = row.person_id;

    try {
        const answer = await fetch(route('teams.respond', [props.team.id, row.person_id]), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ response }),
        });

        const data = await answer.json().catch(() => ({}));

        if (answer.ok) {
            row.response = data.response;
            row.response_label = data.response_label;
            tally.value = data.tally;
        }
    } finally {
        busy.value = null;
    }
};

const remove = () => {
    confirming.value = false;
    router.delete(route('teams.destroy', props.team.id));
};
</script>

<template>
    <Head :title="team.name" />

    <AuthenticatedLayout>
        <template #actions>
            <ButtonLink :href="route('teams.index')" rounded class="sm:!hidden" aria-label="Saved teams">
                <i class="pi pi-flag" />
            </ButtonLink>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                        {{ team.name }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ team.date_label ?? 'No date yet' }}
                    </p>
                </div>

                <ButtonLink :href="route('teams.index')" severity="secondary" outlined class="!hidden sm:!inline-flex">
                    <i class="pi pi-flag mr-2" />
                    All teams
                </ButtonLink>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-1.5">
                <Tag :value="`${said.yes} yes`" severity="success" />
                <Tag :value="`${said.no} no`" severity="danger" />
                <Tag
                    :value="`${said.waiting} to ask`"
                    severity="secondary"
                    class="!border !border-gray-500 !bg-transparent !text-gray-700"
                />
            </div>

            <ul class="space-y-2.5">
                <li v-for="row in rows" :key="row.person_id">
                    <Card
                        :pt="{ body: { class: '!p-3 sm:!p-4' } }"
                        :class="row.response === 'no' ? 'opacity-50' : ''"
                    >
                        <template #content>
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <i
                                            v-if="row.response === 'yes'"
                                            class="pi pi-check-circle text-green-600"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="text-lg font-semibold tracking-tight text-gray-900"
                                            :class="row.response === 'no' ? 'line-through' : ''"
                                        >
                                            {{ row.name }}
                                        </span>
                                    </div>

                                    <div v-if="row.tags.length" class="mt-1.5 flex flex-wrap gap-1.5">
                                        <Tag
                                            v-for="tag in row.tags"
                                            :key="tag.label"
                                            :value="tag.label"
                                            severity="secondary"
                                            :class="
                                                tag.colour
                                                    ? ''
                                                    : '!border !border-gray-500 !bg-transparent !text-gray-700'
                                            "
                                            :style="tagStyle(tag.colour)"
                                        />
                                    </div>

                                    <div
                                        v-if="row.phone || row.email"
                                        class="mt-2 flex flex-wrap items-center gap-2"
                                    >
                                        <span v-if="row.phone" class="text-sm text-gray-600">
                                            {{ row.phone }}
                                        </span>

                                        <ButtonLink
                                            v-if="row.phone"
                                            :href="`tel:${row.phone.replace(/[^0-9+]/g, '')}`"
                                            external
                                            rounded
                                            size="small"
                                        >
                                            <i class="pi pi-phone mr-1.5" />
                                            Call
                                        </ButtonLink>

                                        <ButtonLink
                                            v-if="row.phone"
                                            :href="`sms:${row.phone.replace(/[^0-9+]/g, '')}`"
                                            external
                                            severity="secondary"
                                            outlined
                                            rounded
                                            size="small"
                                        >
                                            <i class="pi pi-comment mr-1.5" />
                                            Text
                                        </ButtonLink>

                                        <ButtonLink
                                            v-if="row.email"
                                            :href="`mailto:${row.email}`"
                                            external
                                            severity="secondary"
                                            outlined
                                            rounded
                                            size="small"
                                        >
                                            <i class="pi pi-envelope mr-1.5" />
                                            Email
                                        </ButtonLink>
                                    </div>
                                </div>

                                <!-- What they said. -->
                                <div class="flex shrink-0 flex-col gap-1.5">
                                    <Button
                                        icon="pi pi-check"
                                        label="Yes"
                                        size="small"
                                        :severity="row.response === 'yes' ? 'success' : 'secondary'"
                                        :outlined="row.response !== 'yes'"
                                        :aria-pressed="row.response === 'yes'"
                                        :aria-label="`${row.name} said yes`"
                                        @click="mark(row, 'yes')"
                                    />

                                    <Button
                                        icon="pi pi-times"
                                        label="No"
                                        size="small"
                                        :severity="row.response === 'no' ? 'danger' : 'secondary'"
                                        :outlined="row.response !== 'no'"
                                        :aria-pressed="row.response === 'no'"
                                        :aria-label="`${row.name} said no`"
                                        @click="mark(row, 'no')"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>
                </li>
            </ul>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-5">
                <Button
                    label="Delete this team"
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    @click="confirming = true"
                />

                <ButtonLink :href="route('teams.build')" severity="secondary" outlined>
                    <i class="pi pi-bolt mr-2" />
                    Build another
                </ButtonLink>
            </div>
        </div>

        <Modal :show="confirming" max-width="md" @close="confirming = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Delete {{ team.name }}?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    The people themselves stay in your rolodex — only this team and what they said
                    is removed.
                </p>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    <Button label="Keep it" severity="secondary" outlined @click="confirming = false" />
                    <Button label="Delete" severity="danger" @click="remove" />
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
