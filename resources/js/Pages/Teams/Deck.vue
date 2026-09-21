<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ButtonLink from '@/Components/ButtonLink.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PersonPhoto from '@/Components/PersonPhoto.vue';
import TextInput from '@/Components/TextInput.vue';
import { tagStyle } from '@/tagColour';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/*
 * Step two: everyone eligible, one at a time, in a random order. Right keeps them,
 * left lets them go, and nothing is saved until the deck runs out.
 */
const props = defineProps({
    questions: {
        type: Array,
        default: () => [],
    },
    answers: {
        type: Object,
        default: () => ({}),
    },
    name: {
        type: String,
        default: '',
    },
    date: {
        type: String,
        default: null,
    },
    eligible: {
        type: Array,
        default: () => [],
    },
});

const index = ref(0);
const kept = ref([]);
const dropped = ref([]);
const history = ref([]);

const teamName = ref(props.name);
const teamDate = ref(props.date ?? '');
const saving = ref(false);

const current = computed(() => props.eligible[index.value] ?? null);
const finished = computed(() => index.value >= props.eligible.length);
const remaining = computed(() => Math.max(0, props.eligible.length - index.value));

/** Dragging the card. */
const dx = ref(0);
const dy = ref(0);
let dragging = null;

const THRESHOLD = 96;

const decide = (keep) => {
    if (current.value === null) {
        return;
    }

    (keep ? kept : dropped).value.push(current.value.id);
    history.value.push({ id: current.value.id, keep });

    index.value += 1;
    dx.value = 0;
    dy.value = 0;
};

const undo = () => {
    const last = history.value.pop();

    if (last === undefined) {
        return;
    }

    if (last.keep) {
        kept.value = kept.value.filter((id) => id !== last.id);
    } else {
        dropped.value = dropped.value.filter((id) => id !== last.id);
    }

    index.value -= 1;
    dx.value = 0;
    dy.value = 0;
};

const startDrag = (event) => {
    dragging = { x: event.clientX, y: event.clientY };

    try {
        event.currentTarget.setPointerCapture?.(event.pointerId);
    } catch {
        // Some pointers cannot be captured; dragging works without it.
    }
};

const onDrag = (event) => {
    if (dragging === null) {
        return;
    }

    dx.value = event.clientX - dragging.x;
    dy.value = (event.clientY - dragging.y) * 0.35;
};

const endDrag = () => {
    if (dragging === null) {
        return;
    }

    dragging = null;

    if (Math.abs(dx.value) > THRESHOLD) {
        decide(dx.value > 0);

        return;
    }

    dx.value = 0;
    dy.value = 0;
};

const cardStyle = computed(() => ({
    transform: `translate(${dx.value}px, ${dy.value}px) rotate(${dx.value / 26}deg)`,
    transition: dragging === null ? 'transform 180ms ease-out' : 'none',
}));

const leaning = computed(() => Math.abs(dx.value) < 32);

const verdict = computed(() => {
    if (leaning.value) {
        return null;
    }

    return dx.value > 0 ? 'yes' : 'no';
});

/** Arrow keys, for a laptop. */
const onKey = (event) => {
    if (event.key === 'ArrowRight') {
        decide(true);
    }

    if (event.key === 'ArrowLeft') {
        decide(false);
    }

    if (event.key === 'Backspace') {
        undo();
    }
};

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const save = () => {
    saving.value = true;

    router.post(
        route('teams.store'),
        {
            name: teamName.value,
            tournament_date: teamDate.value === '' ? null : teamDate.value,
            person_ids: kept.value,
        },
        { onFinish: () => (saving.value = false) },
    );
};
</script>

<template>
    <Head title="Building a team" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Team Builder</h1>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="history.length"
                        link
                        size="small"
                        icon="pi pi-undo"
                        label="Undo"
                        @click="undo"
                    />
                    <ButtonLink :href="route('teams.build')" severity="secondary" outlined size="small">
                        Change questions
                    </ButtonLink>
                </div>
            </div>

            <!-- Nothing to work through -->
            <Card v-if="eligible.length === 0">
                <template #content>
                    <div class="py-8 text-center">
                        <p class="text-lg font-semibold text-gray-900">Nobody matches that.</p>
                        <p class="mt-2 text-sm text-gray-600">
                            Loosen the questions, or add more people to the rolodex.
                        </p>
                        <ButtonLink :href="route('teams.build')" class="mt-5">
                            Change the questions
                        </ButtonLink>
                    </div>
                </template>
            </Card>

            <template v-else>
                <p class="mb-3 text-sm text-gray-600">
                    <span class="font-medium text-gray-900">{{ kept.length }}</span> kept ·
                    <span class="font-medium text-gray-900">{{ dropped.length }}</span> let go ·
                    <span class="font-medium text-gray-900">{{ remaining }}</span> to go
                </p>

                <!-- The deck -->
                <div v-if="!finished" class="relative">
                    <div
                        class="relative touch-none select-none"
                        :style="cardStyle"
                        @pointerdown.prevent="startDrag"
                        @pointermove.prevent="onDrag"
                        @pointerup="endDrag"
                        @pointercancel="endDrag"
                        @pointerleave="endDrag"
                    >
                        <Card :pt="{ body: { class: '!p-4' } }">
                            <template #content>
                                <div class="flex flex-col items-center text-center">
                                    <PersonPhoto :src="current.photo_url" :name="current.name" size="xl" />

                                    <p class="mt-3 text-xl font-semibold tracking-tight text-gray-900">
                                        {{ current.name }}
                                    </p>

                                    <div v-if="current.tags.length" class="mt-2 flex flex-wrap justify-center gap-1.5">
                                        <Tag
                                            v-for="tag in current.tags"
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

                                    <p v-if="current.phone" class="mt-3 text-sm text-gray-600">
                                        {{ current.phone }}
                                    </p>
                                </div>
                            </template>
                        </Card>

                        <!-- The verdict, as the card leans. -->
                        <span
                            v-if="verdict"
                            class="pointer-events-none absolute top-4 rounded-md border-4 px-3 py-1 text-2xl font-black uppercase tracking-widest"
                            :class="
                                verdict === 'yes'
                                    ? 'left-4 rotate-[-12deg] border-green-600 text-green-600'
                                    : 'right-4 rotate-[12deg] border-red-600 text-red-600'
                            "
                        >
                            {{ verdict === 'yes' ? 'In' : 'Out' }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center justify-center gap-3">
                        <Button
                            label="No"
                            icon="pi pi-times"
                            severity="danger"
                            outlined
                            @click="decide(false)"
                        />

                        <Button
                            label="Yes"
                            icon="pi pi-check"
                            @click="decide(true)"
                        />
                    </div>

                    <p class="mt-3 text-center text-xs text-gray-500">
                        Swipe the card, or use the arrows: right to add, left to pass.
                    </p>
                </div>

                <!-- Deck finished: name it and save -->
                <Card v-else>
                    <template #content>
                        <div class="space-y-5">
                            <div>
                                <p class="text-lg font-semibold text-gray-900">
                                    That is everyone — {{ kept.length }} in, {{ dropped.length }} out.
                                </p>
                                <p class="mt-1 text-sm text-gray-600">
                                    Name it and save, then work through asking them.
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="team-name" value="Team name" />

                                    <TextInput
                                        id="team-name"
                                        v-model="teamName"
                                        type="text"
                                        placeholder="Friday night doubles"
                                        autocomplete="off"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="team-date" value="Tournament date" />

                                    <TextInput id="team-date" v-model="teamDate" type="date" />
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-5">
                                <Button
                                    label="Back"
                                    severity="secondary"
                                    outlined
                                    icon="pi pi-undo"
                                    @click="undo"
                                />

                                <Button
                                    label="Save team"
                                    icon="pi pi-check"
                                    :disabled="kept.length === 0 || teamName.trim() === '' || saving"
                                    @click="save"
                                />
                            </div>

                            <p v-if="kept.length === 0" class="text-xs text-gray-500">
                                Nobody was kept, so there is nothing to save yet.
                            </p>
                        </div>
                    </template>
                </Card>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
