<script setup>
import BulkAnswersModal from '@/Components/BulkAnswersModal.vue';
import QuickEditPersonModal from '@/Components/QuickEditPersonModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    people: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Array,
        default: () => [],
    },
});

const term = ref(props.filters.q ?? '');

/** categoryId => array of selected values, held locally so chips feel instant. */
const selected = ref(
    Object.fromEntries(
        props.filterOptions.map((category) => [
            category.id,
            [...(props.filters.categories?.[category.id] ?? [])],
        ]),
    ),
);

const showFilters = ref(
    Object.values(selected.value).some((values) => values.length > 0),
);

const activeCount = computed(() =>
    Object.values(selected.value).reduce((total, values) => total + values.length, 0),
);

const initials = (name) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

const editingId = ref(null);
const editingMode = ref('details');

const openQuickEdit = (id, mode = 'details') => {
    editingId.value = id;
    editingMode.value = mode;
};

const onQuickEditSaved = () => {
    router.reload({ only: ['people'] });
};

// Selection mode for applying details to several people at once. Named `picked`
// because `selected` already holds the filter chip state above.
const selecting = ref(false);
const picked = ref([]);
const bulkOpen = ref(false);

const toggleSelecting = () => {
    selecting.value = !selecting.value;

    if (!selecting.value) {
        picked.value = [];
    }
};

const inSelection = (id) => picked.value.includes(id);

const toggleSelected = (id) => {
    const index = picked.value.indexOf(id);

    if (index === -1) {
        picked.value.push(id);
    } else {
        picked.value.splice(index, 1);
    }
};

const clearSelection = () => {
    picked.value = [];
};

const onBulkApplied = () => {
    clearSelection();
    selecting.value = false;
};

const visit = (page = 1) => {
    const params = new URLSearchParams();

    if (term.value) {
        params.append('q', term.value);
    }

    Object.entries(selected.value).forEach(([categoryId, values]) => {
        if (values.length) {
            params.append(`f[${categoryId}]`, values.join(','));
        }
    });

    if (page > 1) {
        params.append('page', page);
    }

    const query = params.toString();
    const url = query ? `${route('people.index')}?${query}` : route('people.index');

    router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true });
};

// Live search: the whole point of this screen is finding someone fast.
let debounce;
watch(term, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => visit(), 250);
});

// SelectButton keeps the array in step with the chips; the server does the
// filtering, so any change is a visit.
const onFilterChange = () => {
    visit();
};

const clearFilters = () => {
    Object.keys(selected.value).forEach((categoryId) => {
        selected.value[categoryId] = [];
    });

    visit();
};

const clearSearch = () => {
    term.value = '';
};
</script>

<template>
    <Head :title="term ? `Search: ${term}` : 'People'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">People</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ people.total }}
                        {{ people.total === 1 ? 'person' : 'people' }}
                        <span v-if="term">matching “{{ term }}”</span>
                    </p>
                </div>

                <Button asChild>
                    <Link :href="route('people.create')">
                        <i class="pi pi-plus mr-2" />
                        Add person
                    </Link>
                </Button>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                    id="search"
                    v-model="term"
                    type="search"
                    fluid
                    placeholder="Name, phone, email or notes…"
                    autocomplete="off"
                />
            </IconField>

            <!-- Filters and selection -->
            <div v-if="filterOptions.length" class="mt-5">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        severity="secondary"
                        outlined
                        size="small"
                        :aria-expanded="showFilters"
                        @click="showFilters = !showFilters"
                    >
                        <i class="pi pi-filter mr-2" />
                        {{ showFilters ? 'Hide filters' : 'Filters' }}
                        <Badge v-if="activeCount" :value="activeCount" class="ml-2" />
                    </Button>

                    <Button
                        v-if="activeCount"
                        link
                        size="small"
                        label="Clear filters"
                        @click="clearFilters"
                    />

                    <Button
                        :severity="selecting ? 'primary' : 'secondary'"
                        :outlined="!selecting"
                        size="small"
                        :aria-pressed="selecting"
                        :label="selecting ? 'Done selecting' : 'Select people'"
                        @click="toggleSelecting"
                    />
                </div>

                <div v-if="showFilters" class="mt-4 space-y-4">
                    <div v-for="category in filterOptions" :key="category.id">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ category.name }}
                        </p>

                        <SelectButton
                            v-model="selected[category.id]"
                            :options="category.chips"
                            optionLabel="label"
                            optionValue="value"
                            multiple
                            allowEmpty
                            size="small"
                            @update:modelValue="onFilterChange"
                        />
                    </div>
                </div>
            </div>

            <!-- Empty states -->
            <Card v-if="people.data.length === 0" class="mt-8">
                <template #content>
                    <div class="py-8 text-center">
                        <template v-if="term || activeCount">
                            <p class="text-lg font-semibold text-gray-900">
                                Nobody matches that.
                            </p>
                            <p class="mt-2 text-sm text-gray-600">
                                Try a shorter search, or loosen the filters.
                            </p>
                            <div class="mt-5 flex flex-wrap justify-center gap-3">
                                <Button
                                    v-if="activeCount"
                                    severity="secondary"
                                    outlined
                                    label="Clear filters"
                                    @click="clearFilters"
                                />
                                <Button
                                    v-if="term"
                                    severity="secondary"
                                    outlined
                                    label="Clear search"
                                    @click="clearSearch"
                                />
                            </div>
                        </template>

                        <template v-else>
                            <p class="text-lg font-semibold text-gray-900">
                                Your rolodex is empty.
                            </p>
                            <p class="mt-2 text-sm text-gray-600">
                                Add the people you can call when you need a seventh.
                            </p>
                            <Button asChild class="mt-5">
                                <Link :href="route('people.create')">
                                    Add your first person
                                </Link>
                            </Button>
                        </template>
                    </div>
                </template>
            </Card>

            <!-- The list -->
            <ul v-else class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="person in people.data" :key="person.id" class="relative">
                    <Link
                        :href="route('people.show', person.id)"
                        class="block h-full"
                        :tabindex="selecting ? -1 : undefined"
                    >
                        <Card
                            class="h-full"
                            :class="
                                inSelection(person.id)
                                    ? 'ring-2 ring-blue-500'
                                    : 'transition-shadow hover:shadow-md'
                            "
                        >
                            <template #content>
                                <div class="flex gap-3 pb-8">
                                    <Avatar
                                        v-if="person.photo_url"
                                        :image="person.photo_url"
                                        shape="square"
                                        size="large"
                                    />
                                    <Avatar
                                        v-else
                                        :label="initials(person.name)"
                                        shape="square"
                                        size="large"
                                        class="bg-gray-100 text-gray-500"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold leading-tight text-gray-900">
                                            {{ person.name }}
                                        </p>

                                        <p v-if="person.phone" class="mt-2 text-sm text-gray-700">
                                            {{ person.phone }}
                                        </p>

                                        <p
                                            v-if="person.email"
                                            class="mt-0.5 break-all text-sm text-gray-500"
                                        >
                                            {{ person.email }}
                                        </p>

                                        <p
                                            v-if="person.notes_excerpt"
                                            class="mt-3 border-t border-gray-200 pt-2 text-xs leading-relaxed text-gray-500"
                                        >
                                            {{ person.notes_excerpt }}
                                        </p>

                                        <div
                                            v-if="person.tags.length"
                                            class="mt-3 flex flex-wrap gap-1.5"
                                        >
                                            <Tag
                                                v-for="tag in person.tags"
                                                :key="tag"
                                                :value="tag"
                                                severity="secondary"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </Link>

                    <!-- While selecting, this sits over the whole card so a click
                         toggles the person instead of following the link. -->
                    <button
                        v-if="selecting"
                        type="button"
                        class="absolute inset-0 z-10 cursor-pointer rounded-lg border-2 border-transparent"
                        :class="inSelection(person.id) ? 'border-blue-500 bg-blue-500/5' : ''"
                        :aria-pressed="inSelection(person.id)"
                        :aria-label="`${inSelection(person.id) ? 'Deselect' : 'Select'} ${person.name}`"
                        @click="toggleSelected(person.id)"
                    ></button>

                    <span v-if="selecting" class="absolute right-3 top-3 z-20">
                        <i
                            v-if="inSelection(person.id)"
                            class="pi pi-check-circle text-xl text-blue-600"
                            aria-hidden="true"
                        />
                        <i
                            v-else
                            class="pi pi-circle text-xl text-gray-300"
                            aria-hidden="true"
                        />
                    </span>

                    <div v-if="!selecting" class="absolute bottom-3 right-3 z-20 flex gap-1.5">
                        <Button
                            icon="pi pi-pencil"
                            severity="secondary"
                            outlined
                            rounded
                            size="small"
                            :title="`Edit details for ${person.name}`"
                            :aria-label="`Edit details for ${person.name}`"
                            @click.stop.prevent="openQuickEdit(person.id, 'details')"
                        />

                        <Button
                            icon="pi pi-tag"
                            severity="secondary"
                            outlined
                            rounded
                            size="small"
                            :title="`Edit categories for ${person.name}`"
                            :aria-label="`Edit categories for ${person.name}`"
                            @click.stop.prevent="openQuickEdit(person.id, 'tags')"
                        />
                    </div>
                </li>
            </ul>

            <QuickEditPersonModal
                :person-id="editingId"
                :mode="editingMode"
                @close="editingId = null"
                @saved="onQuickEditSaved"
            />

            <BulkAnswersModal
                :show="bulkOpen"
                :person-ids="picked"
                @close="bulkOpen = false"
                @applied="onBulkApplied"
            />

            <!-- Bulk actions, pinned to the bottom while a selection exists -->
            <div v-if="picked.length" class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white">
                <div
                    class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8"
                >
                    <p class="text-sm font-medium text-gray-900">
                        {{ picked.length }} selected
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            severity="secondary"
                            outlined
                            label="Clear"
                            @click="clearSelection"
                        />
                        <Button label="Apply details" @click="bulkOpen = true" />
                    </div>
                </div>
            </div>

            <Paginator
                v-if="people.total > people.per_page"
                :first="(people.current_page - 1) * people.per_page"
                :rows="people.per_page"
                :total="people.total"
                :rowsPerPageOptions="[people.per_page]"
                class="mt-8"
                @page="visit($event.page + 1)"
            />
        </div>
    </AuthenticatedLayout>
</template>
