<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';

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

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-16">
        <div class="w-full max-w-2xl">
            <Card>
                <template #content>
                    <Tag value="Private rolodex" severity="secondary" />

                    <h1 class="mt-4 text-2xl font-semibold text-gray-900 sm:text-3xl">
                        Volleyball Rolodex
                    </h1>

                    <p class="mt-4 text-sm leading-relaxed text-gray-600">
                        Keep track of everyone you can call to fill a court. Who sets, who hits,
                        who plays on which side of the net, who's under six feet — plus phone
                        numbers, notes and a photo. Search it in a second.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <PrimaryButton
                            v-if="canRegister"
                            type="button"
                            @click="router.visit(route('register'))"
                        >
                            Create your rolodex
                        </PrimaryButton>

                        <SecondaryButton
                            v-if="canLogin"
                            type="button"
                            @click="router.visit(route('login'))"
                        >
                            Log in
                        </SecondaryButton>
                    </div>
                </template>
            </Card>

            <p class="mt-6 text-center text-xs text-gray-500">
                Laravel {{ laravelVersion }} · PHP {{ phpVersion }}
            </p>
        </div>
    </div>
</template>
