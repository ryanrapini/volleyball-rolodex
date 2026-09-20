<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Volleyball Rolodex" />

    <div class="min-h-screen bg-paper">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-16">
            <span
                class="inline-flex w-fit items-center border-2 border-ink bg-riso-pink px-3 py-1 font-mono text-xs font-semibold uppercase tracking-widest text-ink shadow-print-sm"
            >
                Private rolodex
            </span>

            <h1 class="mt-6 font-sans text-4xl font-bold uppercase leading-none tracking-tight text-ink sm:text-6xl">
                Volleyball<br />
                <span class="text-riso-blue">Rolodex</span>
            </h1>

            <p class="mt-6 max-w-xl font-mono text-sm leading-relaxed text-ink/80">
                Keep track of everyone you can call to fill a court. Who sets, who hits, who
                plays on which side of the net, who's under six feet — plus phone numbers,
                notes and a photo. Search it in a second.
            </p>

            <div class="mt-10 flex flex-wrap gap-3">
                <Link v-if="canRegister" :href="route('register')" class="btn btn-primary">
                    Create your rolodex
                </Link>
                <Link v-if="canLogin" :href="route('login')" class="btn btn-secondary">
                    Log in
                </Link>
            </div>

            <p class="mt-16 font-mono text-[0.7rem] uppercase tracking-widest text-ink/40">
                Laravel {{ laravelVersion }} · PHP {{ phpVersion }}
            </p>
        </div>
    </div>
</template>
