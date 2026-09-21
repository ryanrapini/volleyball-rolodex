<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ButtonLink from '@/Components/ButtonLink.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';
import SelectButton from 'primevue/selectbutton';
import { reactive, ref } from 'vue';

/*
 * Step one of Team Builder: what this team is for, and what it has to be able to
 * do. Every question is optional — the ones left alone simply do not narrow
 * anything down.
 */
const props = defineProps({
    questions: {
        type: Array,
        default: () => [],
    },
    ready: {
        type: Boolean,
        default: false,
    },
});

const name = ref('');
const date = ref('');
const answers = reactive(
    Object.fromEntries(props.questions.map((question) => [question.id, []])),
);

const start = () => {
    const params = new URLSearchParams();

    if (name.value.trim() !== '') {
        params.set('name', name.value.trim());
    }

    if (date.value !== '') {
        params.set('date', date.value);
    }

    Object.entries(answers).forEach(([categoryId, values]) => {
        if (values.length > 0) {
            params.set(`a[${categoryId}]`, values.join(','));
        }
    });

    router.visit(`${route('teams.deck')}?${params.toString()}`);
};
</script>

<template>
    <Head title="Team Builder" />

    <AuthenticatedLayout>
        <template #actions>
            <ButtonLink :href="route('teams.index')" rounded class="sm:!hidden" aria-label="Saved teams">
                <i class="pi pi-flag" />
            </ButtonLink>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Team Builder</h1>

                <ButtonLink :href="route('teams.index')" severity="secondary" outlined class="!hidden sm:!inline-flex">
                    <i class="pi pi-flag mr-2" />
                    Saved teams
                </ButtonLink>
            </div>

            <Message v-if="!ready" severity="info" :closable="false" class="mb-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        No categories are set up for Team Builder yet. Tick "Ask me this when
                        building a team" on the ones worth asking about.
                    </span>

                    <ButtonLink :href="route('categories.index')" size="small" class="shrink-0">
                        Categories
                    </ButtonLink>
                </div>
            </Message>

            <Card>
                <template #content>
                    <div class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="team-name" value="What is it for?" />

                                <TextInput
                                    id="team-name"
                                    v-model="name"
                                    type="text"
                                    placeholder="Friday night doubles"
                                    autocomplete="off"
                                />
                            </div>

                            <div>
                                <InputLabel for="team-date" value="When?" />

                                <TextInput id="team-date" v-model="date" type="date" />
                            </div>
                        </div>

                        <div v-if="ready" class="space-y-5 border-t border-gray-200 pt-5">
                            <p class="text-sm text-gray-600">
                                Answer what matters for this one. Anything you leave alone is not
                                filtered on.
                            </p>

                            <div v-for="question in questions" :key="question.id">
                                <p
                                    class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    {{ question.name }}
                                </p>

                                <SelectButton
                                    v-model="answers[question.id]"
                                    :options="question.chips"
                                    option-label="label"
                                    option-value="value"
                                    multiple
                                    allow-empty
                                    size="small"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-5">
                            <ButtonLink :href="route('people.index')" severity="secondary" outlined label="Cancel" />

                            <Button
                                label="Find players"
                                icon="pi pi-bolt"
                                :disabled="!ready"
                                @click="start"
                            />
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
