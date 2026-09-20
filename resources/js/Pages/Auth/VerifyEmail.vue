<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <h1 class="font-sans text-2xl font-bold uppercase tracking-tight text-ink">
            Verify your email
        </h1>

        <p class="mt-2 font-mono text-xs leading-relaxed text-ink/70">
            Click the link we just emailed you to finish setting up your account. Didn't get
            it? We'll send another.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mt-4 inline-block bg-riso-pink/25 px-2 py-1 font-mono text-xs font-semibold text-ink"
        >
            A new verification link has been sent to your email address.
        </div>

        <form class="mt-6" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <PrimaryButton :disabled="form.processing">
                    Resend verification email
                </PrimaryButton>

                <Link :href="route('logout')" method="post" as="button" class="link text-sm">
                    Log out
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
