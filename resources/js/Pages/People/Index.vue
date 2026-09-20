<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    people: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const term = ref(props.filters.q ?? '');
let debounce;

// Live search: the whole point of this screen is finding someone fast.
watch(term, (value) => {
    clearTimeout(debounce);

    debounce = setTimeout(() => {
        router.get(
            route('people.index'),
            value ? { q: value } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 250);
});

const clearSearch = () => {
    term.value = '';
};

const initials = (name) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
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

            <!-- Empty states -->
            <div v-if="people.data.length === 0" class="card mt-8 p-8 text-center shadow-print-sm">
                <template v-if="term">
                    <p class="font-sans text-lg font-bold text-ink">Nobody matches “{{ term }}”.</p>
                    <p class="mt-2 font-mono text-xs text-ink/60">
                        Try a shorter search, or clear it to see everyone.
                    </p>
                    <button type="button" class="btn btn-secondary mt-5" @click="clearSearch">
                        Clear search
                    </button>
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
                <li v-for="person in people.data" :key="person.id">
                    <Link
                        :href="route('people.show', person.id)"
                        class="card flex h-full gap-3 p-4 transition-transform duration-100 hover:-translate-y-0.5 hover:shadow-print-sm"
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

                            <span
                                v-if="person.tags.length"
                                class="mt-3 flex flex-wrap gap-1.5"
                            >
                                <span v-for="tag in person.tags" :key="tag" class="tag">
                                    {{ tag }}
                                </span>
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>

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
