<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Message from 'primevue/message';

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

        <h1 class="text-2xl font-semibold text-gray-900">Verify your email</h1>

        <p class="mt-2 text-sm text-gray-600">
            Click the link we just emailed you to finish setting up your account. Didn't get
            it? We'll send another.
        </p>

        <Message v-if="verificationLinkSent" severity="success" :closable="false" class="mt-4">
            A new verification link has been sent to your email address.
        </Message>

        <form class="mt-6" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <PrimaryButton :disabled="form.processing">
                    Resend verification email
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="cursor-pointer text-sm font-medium text-blue-600 hover:underline"
                >
                    Log out
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
