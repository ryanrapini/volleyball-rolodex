<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Profile</h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <Card>
                    <template #content>
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                            class="max-w-xl"
                        />
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <UpdatePasswordForm class="max-w-xl" />
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <DeleteUserForm class="max-w-xl" />
                    </template>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
