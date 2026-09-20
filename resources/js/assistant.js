import { ref } from 'vue';

/*
 * Assistant state lives at module scope on purpose. Inertia swaps the page
 * component on every visit, so a transcript held inside the drawer component
 * would vanish the first time someone clicks a card. The module survives.
 */

/** @type {import('vue').Ref<Array<{role: string, content: string, actions?: Array<object>}>>} */
export const messages = ref([]);

export const draft = ref('');

export const open = ref(false);

export const sending = ref(false);

export const error = ref('');
