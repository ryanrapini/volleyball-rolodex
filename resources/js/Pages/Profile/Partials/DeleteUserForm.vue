<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

/*
 * TextInput is a thin wrapper around PrimeVue's InputText, so the ref may be
 * the wrapper (which exposes no methods) rather than the input itself. Reach
 * for focus() when it exists, otherwise fall back to the rendered root element.
 */
const focusField = (target) => {
    const field = target?.value;

    if (!field) {
        return;
    }

    if (typeof field.focus === 'function') {
        field.focus();
    } else {
        field.$el?.focus?.();
    }
};

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => focusField(passwordInput));
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => focusField(passwordInput),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-semibold text-gray-900">Delete Account</h2>

            <p class="mt-1 text-sm text-gray-600">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Delete Account</DangerButton>

        <Modal :show="confirmingUserDeletion" max-width="md" @close="closeModal">
            <h2 class="text-lg font-semibold text-gray-900">
                Are you sure you want to delete your account?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Please enter your password to confirm
                you would like to permanently delete your account.
            </p>

            <div class="mt-6">
                <InputLabel for="password" value="Password" class="sr-only" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Password"
                    @keyup.enter="deleteUser"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>

                <DangerButton :disabled="form.processing" @click="deleteUser">
                    Delete Account
                </DangerButton>
            </div>
        </Modal>
    </section>
</template>
