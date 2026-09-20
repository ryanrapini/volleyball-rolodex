<script setup>
import { draft, error, messages, open, sending } from '@/assistant';
import { router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import { nextTick, ref, watch } from 'vue';

const page = usePage();
const transcript = ref(null);

const xsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const scrollToLatest = async () => {
    await nextTick();
    const box = transcript.value;

    if (box) {
        box.scrollTop = box.scrollHeight;
    }
};

watch(messages, scrollToLatest, { deep: true });
watch(open, (isOpen) => {
    if (isOpen) {
        scrollToLatest();
    }
});

const send = async () => {
    const text = draft.value.trim();

    if (text === '' || sending.value) {
        return;
    }

    messages.value.push({ role: 'user', content: text });
    draft.value = '';
    error.value = '';
    sending.value = true;

    try {
        const response = await fetch(route('ai.chat'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ messages: messages.value }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            // Laravel middleware replies with {message}; our own handlers use {error}.
            error.value = data.error ?? data.message ?? 'Something went wrong.';
            messages.value.pop();
            draft.value = text;

            return;
        }

        messages.value.push({
            role: 'assistant',
            content: data.reply ?? '',
            actions: data.actions ?? [],
        });

        if ((data.actions ?? []).length && page.component === 'People/Index') {
            router.reload({ only: ['people'] });
        }
    } catch {
        error.value = 'Could not reach the assistant.';
        messages.value.pop();
        draft.value = text;
    } finally {
        sending.value = false;
    }
};

// --- voice -----------------------------------------------------------------

const recording = ref(false);
const transcribing = ref(false);
const micProblem = ref('');
let recorder = null;
let chunks = [];

const startRecording = async () => {
    micProblem.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        micProblem.value = 'This browser has no microphone access.';

        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];

        recorder = new MediaRecorder(stream);
        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        recorder.onstop = async () => {
            stream.getTracks().forEach((track) => track.stop());
            await transcribe(new Blob(chunks, { type: recorder.mimeType || 'audio/webm' }));
        };

        recorder.start();
        recording.value = true;
    } catch {
        micProblem.value = 'Microphone permission was refused.';
    }
};

const stopRecording = () => {
    recorder?.stop();
    recording.value = false;
};

const toggleRecording = () => {
    if (recording.value) {
        stopRecording();
    } else {
        startRecording();
    }
};

const transcribe = async (blob) => {
    transcribing.value = true;

    const body = new FormData();
    body.append('audio', blob, 'voice.webm');

    try {
        const response = await fetch(route('ai.transcribe'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            micProblem.value = data.error ?? data.message ?? 'Could not make that out.';

            return;
        }

        draft.value = draft.value ? `${draft.value} ${data.text}` : data.text;
    } catch {
        micProblem.value = 'Could not send that recording.';
    } finally {
        transcribing.value = false;
    }
};

const clearTranscript = () => {
    messages.value = [];
    error.value = '';
};
</script>

<template>
    <Drawer
        v-model:visible="open"
        position="left"
        :style="{ width: '26rem', maxWidth: '100%' }"
        aria-label="Rolodex assistant"
    >
        <template #header>
            <div class="flex w-full items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Assistant</h2>
                    <p class="text-xs text-gray-500">Talk, and it fills the rolodex</p>
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        v-if="messages.length"
                        link
                        size="small"
                        label="Clear"
                        @click="clearTranscript"
                    />
                    <Button
                        icon="pi pi-times"
                        text
                        rounded
                        severity="secondary"
                        aria-label="Close assistant"
                        @click="open = false"
                    />
                </div>
            </div>
        </template>

        <div ref="transcript" class="flex h-full flex-col gap-3 overflow-y-auto">
            <div v-if="!messages.length" class="rounded-md border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs leading-relaxed text-gray-600">
                    Try:
                    <span class="font-medium text-gray-900">
                        “Marcus Hale sets, BB level, plays Wednesday nights, 555-0100”
                    </span>
                    — or hold the mic and say it out loud.
                </p>
            </div>

            <div
                v-for="(message, index) in messages"
                :key="index"
                class="max-w-[95%] rounded-md p-3"
                :class="
                    message.role === 'user'
                        ? 'ml-auto bg-blue-50 ring-1 ring-blue-100'
                        : 'bg-gray-50 ring-1 ring-gray-200'
                "
            >
                <p class="text-xs font-medium text-gray-500">
                    {{ message.role === 'user' ? 'You' : 'Assistant' }}
                </p>
                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-900">
                    {{ message.content }}
                </p>

                <ul v-if="message.actions?.length" class="mt-2 space-y-1">
                    <li
                        v-for="action in message.actions"
                        :key="action.id"
                        class="flex items-center gap-1.5 text-xs text-gray-700"
                    >
                        <i class="pi pi-check-circle text-green-600" aria-hidden="true" />
                        {{ action.type === 'created' ? 'Added' : 'Updated' }}
                        {{ action.name }}
                    </li>
                </ul>
            </div>

            <p v-if="sending || transcribing" class="text-xs text-gray-500">
                {{ transcribing ? 'Listening back…' : 'Thinking…' }}
            </p>

            <Message v-if="error" severity="error" size="small" variant="simple">
                {{ error }}
            </Message>

            <Message v-if="micProblem" severity="warn" size="small" variant="simple">
                {{ micProblem }}
            </Message>
        </div>

        <template #footer>
            <form class="w-full" @submit.prevent="send">
                <label for="assistant-input" class="mb-1 block text-sm font-medium text-gray-700">
                    Say something
                </label>

                <div class="flex gap-2">
                    <Textarea
                        id="assistant-input"
                        v-model="draft"
                        rows="2"
                        fluid
                        class="resize-none"
                        placeholder="Add someone, or change their details…"
                        @keydown.enter.exact.prevent="send"
                    />

                    <Button
                        :icon="recording ? 'pi pi-stop-circle' : 'pi pi-microphone'"
                        :severity="recording ? 'danger' : 'secondary'"
                        :outlined="!recording"
                        :aria-pressed="recording"
                        :title="recording ? 'Stop and transcribe' : 'Hold to talk'"
                        class="shrink-0 self-stretch"
                        @click="toggleRecording"
                    />
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        Enter sends · Shift+Enter for a new line
                    </p>

                    <Button
                        type="submit"
                        size="small"
                        label="Send"
                        :disabled="sending || draft.trim() === ''"
                    />
                </div>
            </form>
        </template>
    </Drawer>
</template>
