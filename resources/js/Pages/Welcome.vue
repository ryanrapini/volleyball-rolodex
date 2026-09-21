<script setup>
import LoginForm from '@/Pages/Auth/Partials/LoginForm.vue';
import RegisterForm from '@/Pages/Auth/Partials/RegisterForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import Accordion from 'primevue/accordion';
import AccordionContent from 'primevue/accordioncontent';
import AccordionHeader from 'primevue/accordionheader';
import AccordionPanel from 'primevue/accordionpanel';
import ButtonLink from '@/Components/ButtonLink.vue';
import { computed, ref, watch } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();

const signedIn = computed(() => !!page.props.auth?.user);

// Sign up is the default. A failed submit re-renders this same component rather
// than remounting it, so the panel the visitor was using stays open.
const open = ref('register');

const loginForm = ref(null);
const registerForm = ref(null);

// One error bag serves both forms, so the hidden one has to let go of errors
// that belong to the other.
watch(open, (panel) => {
    if (panel === 'login') {
        registerForm.value?.clearErrors();
    }

    if (panel === 'register') {
        loginForm.value?.clearErrors();
    }
});
</script>

<template>
    <Head title="Volleyball Rolodex" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10">
        <div class="flex items-center gap-2.5">
            <span class="mark" aria-hidden="true">
                <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="7">
                    <circle cx="50" cy="50" r="44" />
                    <path d="M50 6C26 26 26 74 50 94" />
                    <path d="M50 6c24 20 24 68 0 88" />
                    <path d="M6 50c22-22 66-22 88 0" />
                </svg>
            </span>

            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                Volleyball Rolodex
            </h1>
        </div>

        <div class="mt-8 w-full max-w-md">
            <ButtonLink
                v-if="signedIn"
                :href="route('people.index')"
                label="Open your rolodex"
                class="w-full"
            />

            <Accordion
                v-else
                v-model:value="open"
                class="overflow-hidden rounded-lg border border-gray-200 bg-white"
            >
                <AccordionPanel v-if="canRegister" value="register">
                    <AccordionHeader>Sign up</AccordionHeader>

                    <AccordionContent>
                        <RegisterForm ref="registerForm" id-prefix="register" />
                    </AccordionContent>
                </AccordionPanel>

                <AccordionPanel v-if="canLogin" value="login">
                    <AccordionHeader>Log in</AccordionHeader>

                    <AccordionContent>
                        <LoginForm
                            ref="loginForm"
                            id-prefix="login"
                            :can-reset-password="true"
                        />
                    </AccordionContent>
                </AccordionPanel>
            </Accordion>
        </div>
    </div>
</template>

<style scoped>
.mark {
    display: block;
    width: 1.6rem;
    height: 1.6rem;
    color: #f237a1;
}

.mark svg {
    width: 100%;
    height: 100%;
}
</style>
