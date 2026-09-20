<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import QuickEditPersonModal from '@/Components/QuickEditPersonModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

const openQuickEdit = (id) => {
    editingId.value = id;
};

const onQuickEditSaved = () => {
    router.reload({ only: ['people'] });
};

const visit = () => {
    const params = new URLSearchParams();

    if (term.value) {
        params.append('q', term.value);
    }

    Object.entries(selected.value).forEach(([categoryId, values]) => {
        if (values.length) {
            params.append(`f[${categoryId}]`, values.join(','));
        }
    });

    const query = params.toString();
    const url = query ? `${route('people.index')}?${query}` : route('people.index');

    router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true });
};

// Live search: the whole point of this screen is finding someone fast.
let debounce;
watch(term, () => {
    clearTimeout(debounce);
    debounce = setTimeout(visit, 250);
});

const toggleChip = (categoryId, value) => {
    const values = selected.value[categoryId];
    const index = values.indexOf(value);

    if (index === -1) {
        values.push(value);
    } else {
        values.splice(index, 1);
    }

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
                    <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
                        People
                    </h1>
                    <p class="mt-1 font-mono text-xs text-ink/60">
                        {{ people.total }}
                        {{ people.total === 1 ? 'person' : 'people' }}
                        <span v-if="term">matching “{{ term }}”</span>
                    </p>
                </div>

                <Link :href="route('people.create')" class="btn btn-primary">Add person</Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="relative">
                <InputLabel for="search" value="Search" />

                <div class="flex gap-2">
                    <input
                        id="search"
                        v-model="term"
                        type="search"
                        class="input"
                        placeholder="Name, phone, email or notes…"
                        autocomplete="off"
                    />

                    <button
                        v-if="term"
                        type="button"
                        class="btn btn-secondary shrink-0"
                        @click="clearSearch"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Chips -->
            <div v-if="filterOptions.length" class="mt-5">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-secondary px-3 py-1.5 text-xs"
                        :aria-expanded="showFilters"
                        @click="showFilters = !showFilters"
                    >
                        {{ showFilters ? 'Hide filters' : 'Filters' }}
                        <span v-if="activeCount">{{ activeCount }}</span>
                    </button>

                    <button
                        v-if="activeCount"
                        type="button"
                        class="link text-xs"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <div v-if="showFilters" class="mt-4 space-y-4 border-l-4 border-riso-blue/30 pl-3">
                    <div v-for="category in filterOptions" :key="category.id">
                        <p class="font-mono text-[0.7rem] uppercase tracking-widest text-ink/50">
                            {{ category.name }}
                        </p>

                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button
                                v-for="chip in category.chips"
                                :key="chip.value"
                                type="button"
                                class="chip"
                                :class="{
                                    'chip-active': selected[category.id].includes(chip.value),
                                }"
                                :aria-pressed="selected[category.id].includes(chip.value)"
                                @click="toggleChip(category.id, chip.value)"
                            >
                                {{ chip.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty states -->
            <div v-if="people.data.length === 0" class="card mt-8 p-8 text-center shadow-print-sm">
                <template v-if="term || activeCount">
                    <p class="font-sans text-lg font-bold text-ink">Nobody matches that.</p>
                    <p class="mt-2 font-mono text-xs text-ink/60">
                        Try a shorter search, or loosen the filters.
                    </p>
                    <div class="mt-5 flex flex-wrap justify-center gap-3">
                        <button
                            v-if="activeCount"
                            type="button"
                            class="btn btn-secondary"
                            @click="clearFilters"
                        >
                            Clear filters
                        </button>
                        <button
                            v-if="term"
                            type="button"
                            class="btn btn-secondary"
                            @click="clearSearch"
                        >
                            Clear search
                        </button>
                    </div>
                </template>

                <template v-else>
                    <p class="font-sans text-lg font-bold text-ink">Your rolodex is empty.</p>
                    <p class="mt-2 font-mono text-xs text-ink/60">
                        Add the people you can call when you need a seventh.
                    </p>
                    <Link :href="route('people.create')" class="btn btn-primary mt-5">
                        Add your first person
                    </Link>
                </template>
            </div>

            <!-- The list -->
            <ul v-else class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="person in people.data" :key="person.id" class="relative">
                    <Link
                        :href="route('people.show', person.id)"
                        class="card flex h-full gap-3 p-4 pb-12 transition-transform duration-100 hover:-translate-y-0.5 hover:shadow-print-sm"
                    >
                        <img
                            v-if="person.photo_url"
                            :src="person.photo_url"
                            alt=""
                            class="h-14 w-14 shrink-0 border-2 border-ink object-cover"
                        />
                        <span
                            v-else
                            class="flex h-14 w-14 shrink-0 items-center justify-center border-2 border-ink bg-riso-pink/20 font-mono text-sm font-semibold text-ink/60"
                        >
                            {{ initials(person.name) }}
                        </span>

                        <span class="min-w-0">
                            <span class="block font-sans text-base font-bold leading-tight text-ink">
                                {{ person.name }}
                            </span>

                            <span
                                v-if="person.phone"
                                class="mt-2 block font-mono text-xs text-ink/80"
                            >
                                {{ person.phone }}
                            </span>

                            <span
                                v-if="person.email"
                                class="mt-0.5 block break-all font-mono text-xs text-ink/60"
                            >
                                {{ person.email }}
                            </span>

                            <span
                                v-if="person.notes_excerpt"
                                class="mt-3 block border-t-2 border-ink/10 pt-2 font-mono text-[0.7rem] leading-relaxed text-ink/60"
                            >
                                {{ person.notes_excerpt }}
                            </span>

                            <span v-if="person.tags.length" class="mt-3 flex flex-wrap gap-1.5">
                                <span v-for="tag in person.tags" :key="tag" class="tag">
                                    {{ tag }}
                                </span>
                            </span>
                        </span>
                    </Link>

                    <button
                        type="button"
                        class="absolute bottom-2 right-2 flex h-8 w-8 items-center justify-center border-2 border-ink bg-white text-sm leading-none shadow-print-sm transition-all duration-100 hover:translate-x-[1px] hover:translate-y-[1px] hover:bg-riso-pink hover:shadow-none"
                        :title="`Quick edit ${person.name}`"
                        :aria-label="`Quick edit ${person.name}`"
                        @click="openQuickEdit(person.id)"
                    >
                        <span aria-hidden="true">✎</span>
                    </button>
                </li>
            </ul>

            <QuickEditPersonModal
                :person-id="editingId"
                @close="editingId = null"
                @saved="onQuickEditSaved"
            />

            <!-- Pagination -->
            <nav
                v-if="people.links.length > 3"
                class="mt-8 flex flex-wrap items-center gap-2"
                aria-label="Pagination"
            >
                <template v-for="(link, index) in people.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="chip"
                        :class="{ 'chip-active': link.active }"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span v-else class="chip cursor-not-allowed opacity-40" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
