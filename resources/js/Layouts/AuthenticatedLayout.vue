<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AssistantDrawer from '@/Components/AssistantDrawer.vue';
import ButtonLink from '@/Components/ButtonLink.vue';
import { open as assistantOpen } from '@/assistant';
import { router, usePage } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import Drawer from 'primevue/drawer';
import Menu from 'primevue/menu';
import Message from 'primevue/message';
import Toolbar from 'primevue/toolbar';
import { computed, ref } from 'vue';

const page = usePage();

const mobileNav = ref(false);
const userMenu = ref(null);

const user = computed(() => page.props.auth.user);

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const navItems = [
    { label: 'People', icon: 'pi pi-users', route: 'people.index', active: 'people.*' },
    { label: 'Categories', icon: 'pi pi-tags', route: 'categories.index', active: 'categories.*' },
];

const userMenuItems = computed(() => [
    { label: user.value?.name ?? '', disabled: true },
    { separator: true },
    {
        label: 'Profile',
        icon: 'pi pi-user',
        command: () => router.visit(route('profile.edit')),
    },
    {
        label: 'Log out',
        icon: 'pi pi-sign-out',
        command: () => router.post(route('logout')),
    },
]);

const go = (routeName) => {
    mobileNav.value = false;
    router.visit(route(routeName));
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-50">
        <Toolbar class="border-b border-gray-200">
            <template #start>
                <ApplicationLogo :href="route('people.index')" />

                <nav class="ml-8 hidden items-center gap-1 md:flex">
                    <ButtonLink
                        v-for="item in navItems"
                        :key="item.label"
                        :href="route(item.route)"
                        :text="!route().current(item.active)"
                        size="small"
                    >
                        <i :class="item.icon" class="mr-2" />
                        {{ item.label }}
                    </ButtonLink>
                </nav>
            </template>

            <template #end>
                <div class="flex items-center gap-2">
                    <div class="hidden md:block">
                        <Button
                            label="Assistant"
                            icon="pi pi-comments"
                            size="small"
                            outlined
                            @click="assistantOpen = true"
                        />
                    </div>

                    <div class="md:hidden">
                        <Button
                            text
                            severity="secondary"
                            icon="pi pi-bars"
                            aria-label="Open menu"
                            @click="mobileNav = true"
                        />
                    </div>

                    <div class="hidden md:block">
                        <Button text severity="secondary" @click="userMenu.toggle($event)">
                            <Avatar :label="initials" shape="circle" size="small" />
                            <span class="ml-2">{{ user.name }}</span>
                            <i class="pi pi-angle-down ml-2 text-xs" />
                        </Button>
                        <Menu ref="userMenu" :model="userMenuItems" popup />
                    </div>
                </div>
            </template>
        </Toolbar>

        <div v-if="$page.props.flash?.status" class="mx-auto w-full max-w-6xl px-4 pt-4 sm:px-6 lg:px-8">
            <Message severity="success" :closable="false">
                {{ $page.props.flash.status }}
            </Message>
        </div>

        <header v-if="$slots.header" class="bg-white">
            <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <Drawer v-model:visible="mobileNav" header="Menu" position="left" class="w-72">
            <div class="flex flex-col gap-1">
                <Button
                    v-for="item in navItems"
                    :key="item.label"
                    :label="item.label"
                    :icon="item.icon"
                    :text="!route().current(item.active)"
                    severity="secondary"
                    class="justify-start"
                    @click="go(item.route)"
                />
                <Button
                    label="Assistant"
                    icon="pi pi-comments"
                    text
                    severity="secondary"
                    class="justify-start"
                    @click="
                        mobileNav = false;
                        assistantOpen = true;
                    "
                />
            </div>

            <Divider />

            <div class="px-2">
                <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                <p class="text-xs text-gray-500">{{ user.email }}</p>
            </div>

            <div class="mt-3 flex flex-col gap-1">
                <Button
                    label="Profile"
                    icon="pi pi-user"
                    text
                    severity="secondary"
                    class="justify-start"
                    @click="go('profile.edit')"
                />
                <Button
                    label="Log out"
                    icon="pi pi-sign-out"
                    text
                    severity="secondary"
                    class="justify-start"
                    @click="router.post(route('logout'))"
                />
            </div>
        </Drawer>

        <AssistantDrawer />
    </div>
</template>
