<script setup>
import { nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { draft, error, messages, open, sending } from '@/assistant';

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
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-ink/40"
            @click="open = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-full max-w-sm flex-col border-r-2 border-ink bg-paper transition-transform duration-200"
            :class="open ? 'translate-x-0' : '-translate-x-full'"
            :inert="!open"
            :aria-hidden="!open"
            aria-label="Rolodex assistant"
        >
            <header class="flex items-center justify-between border-b-2 border-ink bg-white px-4 py-3">
                <div>
                    <h2 class="font-sans text-base font-bold uppercase tracking-tight text-ink">
                        Assistant
                    </h2>
                    <p class="font-mono text-[0.65rem] uppercase tracking-widest text-ink/50">
                        Talk, and it fills the rolodex
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="messages.length"
                        type="button"
                        class="link text-xs"
                        @click="clearTranscript"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        class="btn btn-secondary px-3 py-1 text-xs"
                        @click="open = false"
                    >
                        Close
                    </button>
                </div>
            </header>

            <div ref="transcript" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                <p
                    v-if="!messages.length"
                    class="card p-4 font-mono text-xs leading-relaxed text-ink/70"
                >
                    Try: <span class="text-ink">“Marcus Hale sets, BB level, plays Wednesday
                    nights, 555-0100”</span> — or hold the mic and say it out loud.
                </p>

                <div
                    v-for="(message, index) in messages"
                    :key="index"
                    class="max-w-[95%] border-2 border-ink p-3"
                    :class="message.role === 'user' ? 'ml-auto bg-riso-pink/25' : 'bg-white'"
                >
                    <p class="font-mono text-[0.65rem] uppercase tracking-widest text-ink/50">
                        {{ message.role === 'user' ? 'You' : 'Assistant' }}
                    </p>
                    <p class="mt-1 whitespace-pre-line font-sans text-sm leading-relaxed text-ink">
                        {{ message.content }}
                    </p>

                    <ul v-if="message.actions?.length" class="mt-2 space-y-1">
                        <li
                            v-for="action in message.actions"
                            :key="action.id"
                            class="font-mono text-[0.7rem] text-ink/70"
                        >
                            ✓ {{ action.type === 'created' ? 'Added' : 'Updated' }}
                            {{ action.name }}
                        </li>
                    </ul>
                </div>

                <p
                    v-if="sending || transcribing"
                    class="font-mono text-xs uppercase tracking-widest text-ink/50"
                >
                    {{ transcribing ? 'Listening back…' : 'Thinking…' }}
                </p>

                <p v-if="error" class="bg-riso-pink px-2 py-1 font-mono text-xs text-ink">
                    {{ error }}
                </p>

                <p v-if="micProblem" class="font-mono text-xs text-ink/70">
                    {{ micProblem }}
                </p>
            </div>

            <form class="border-t-2 border-ink bg-white p-3" @submit.prevent="send">
                <label for="assistant-input" class="label">Say something</label>

                <div class="flex gap-2">
                    <textarea
                        id="assistant-input"
                        v-model="draft"
                        rows="2"
                        class="input resize-none"
                        placeholder="Add someone, or change their details…"
                        @keydown.enter.exact.prevent="send"
                    ></textarea>

                    <button
                        type="button"
                        class="btn shrink-0 self-stretch px-3"
                        :class="recording ? 'btn-primary' : 'btn-secondary'"
                        :aria-pressed="recording"
                        :title="recording ? 'Stop and transcribe' : 'Hold to talk'"
                        @click="toggleRecording"
                    >
                        {{ recording ? '■' : '🎙' }}
                    </button>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <p class="font-mono text-[0.65rem] text-ink/50">
                        Enter sends · Shift+Enter for a new line
                    </p>

                    <button
                        type="submit"
                        class="btn btn-primary px-3 py-1.5 text-xs"
                        :disabled="sending || draft.trim() === ''"
                    >
                        Send
                    </button>
                </div>
            </form>
        </aside>
    </Teleport>
</template>
