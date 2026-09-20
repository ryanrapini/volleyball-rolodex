<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import ToggleButton from 'primevue/togglebutton';
import { ref } from 'vue';

defineProps({
    users: {
        type: Array,
        default: () => [],
    },
});

const confirming = ref(null);

const setAi = (user, approved) => {
    router.patch(
        route('admin.users.ai', user.id),
        { approved },
        { preserveScroll: true },
    );
};

const askDelete = (user) => {
    confirming.value = user;
};

const closeDelete = () => {
    confirming.value = null;
};

const destroy = () => {
    router.delete(route('admin.users.destroy', confirming.value.id), {
        preserveScroll: true,
        onFinish: closeDelete,
    });
};
</script>

<template>
    <Head title="Accounts" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">Accounts</h1>
            <p class="mt-1 text-sm text-gray-600">
                A new account cannot use the AI assistant until you switch it on here.
            </p>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <Card>
                <template #content>
                    <DataTable :value="users" dataKey="id" size="small" responsiveLayout="scroll">
                        <Column header="Account">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ data.name }}</span>
                                    <Tag v-if="data.is_admin" value="admin" severity="info" />
                                    <Tag v-if="!data.verified" value="unverified" severity="warn" />
                                </div>
                                <div class="text-sm text-gray-500">{{ data.email }}</div>
                            </template>
                        </Column>

                        <Column header="Joined">
                            <template #body="{ data }">
                                <span class="text-sm text-gray-600">{{ data.joined }}</span>
                            </template>
                        </Column>

                        <Column header="People">
                            <template #body="{ data }">
                                <span class="text-sm text-gray-600">{{ data.people_count }}</span>
                            </template>
                        </Column>

                        <Column header="Categories">
                            <template #body="{ data }">
                                <span class="text-sm text-gray-600">{{ data.categories_count }}</span>
                            </template>
                        </Column>

                        <Column header="AI assistant">
                            <template #body="{ data }">
                                <ToggleButton
                                    :modelValue="data.ai_approved"
                                    onLabel="On"
                                    offLabel="Off"
                                    :disabled="data.is_self"
                                    @update:modelValue="(value) => setAi(data, value)"
                                />
                            </template>
                        </Column>

                        <Column>
                            <template #body="{ data }">
                                <Button
                                    v-if="!data.is_self && !data.is_admin"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    :aria-label="`Delete ${data.name}`"
                                    @click="askDelete(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog
            :visible="!!confirming"
            modal
            :style="{ width: '26rem' }"
            @update:visible="closeDelete"
        >
            <h2 class="text-lg font-semibold text-gray-900">
                Delete {{ confirming?.name }}?
            </h2>

            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                This removes the account along with their rolodex, their categories and their
                photos. There is no undo.
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <Button severity="secondary" outlined label="Keep the account" @click="closeDelete" />
                <Button severity="danger" label="Yes, delete" @click="destroy" />
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
