<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

// The tag strip is duplicated so the loop has no seam.
const tags = [
    'Can set',
    'Under 6 ft',
    'Plays beach',
    'BB level',
    'Doubles',
    'Wednesday nights',
    'Left side',
    'Passes well',
    'Indoor',
    'Available Sunday',
];

const features = [
    {
        icon: 'pi pi-sliders-h',
        title: 'Your own categories',
        body: 'Can set. Under 6 ft. Plays beach. Define whatever you actually ask before you text someone.',
    },
    {
        icon: 'pi pi-search',
        title: 'Search in a second',
        body: 'Type a name, or filter by the answers. The list narrows while you type.',
    },
    {
        icon: 'pi pi-phone',
        title: 'Tap to call or text',
        body: 'One tap opens your dialler, another opens a message. No copying numbers off the screen.',
    },
    {
        icon: 'pi pi-comments',
        title: 'Say it, and it is in',
        body: '“Marcus sets, BB level, plays Wednesday nights” and the assistant fills in the card.',
    },
];

const roster = [
    { name: 'Marcus Hale', tags: ['Can set', 'BB', 'Wednesday'], tint: 'pink' },
    { name: 'Dana Okafor', tags: ['Under 6 ft', 'Beach', 'Left side'], tint: 'blue' },
    { name: 'Sam Ruiz', tags: ['Can hit', 'Doubles', 'Sunday'], tint: 'pink' },
];

const revealed = ref(false);

let observer = null;

/*
 * Sections below the fold rise into place as they arrive. Without
 * IntersectionObserver, or when the reader has asked for less motion, they are
 * simply visible from the start.
 */
onMounted(() => {
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealed.value = true;

        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );

    document.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Find a seventh" />

    <div class="court min-h-screen overflow-x-hidden text-white">
        <!-- drifting glow behind everything -->
        <div class="glow" aria-hidden="true"></div>

        <header class="relative z-20 mx-auto flex w-full max-w-5xl items-center justify-between px-5 py-5">
            <div class="flex items-center gap-2.5">
                <span class="ball-mark" aria-hidden="true">
                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="7">
                        <circle cx="50" cy="50" r="44" />
                        <path d="M50 6C26 26 26 74 50 94" />
                        <path d="M50 6c24 20 24 68 0 88" />
                        <path d="M6 50c22-22 66-22 88 0" />
                    </svg>
                </span>
                <span class="text-sm font-semibold tracking-tight sm:text-base">
                    Volleyball Rolodex
                </span>
            </div>

            <Link
                v-if="signedIn"
                :href="route('people.index')"
                class="text-sm font-medium text-white/70 transition-colors hover:text-white"
            >
                Open your rolodex
            </Link>
            <Link
                v-else-if="canLogin"
                :href="route('login')"
                class="text-sm font-medium text-white/70 transition-colors hover:text-white"
            >
                Log in
            </Link>
        </header>

        <!-- hero -->
        <section class="relative z-10 mx-auto w-full max-w-5xl px-5 pb-16 pt-8 sm:pb-24 sm:pt-14">
            <div class="ball" aria-hidden="true">
                <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="50" cy="50" r="46" />
                    <path d="M50 4C24 26 24 74 50 96" />
                    <path d="M50 4c26 22 26 70 0 92" />
                    <path d="M4 50c22-22 70-22 92 0" />
                    <path d="M50 4v92" opacity="0.35" />
                </svg>
            </div>

            <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-white/70 backdrop-blur">
                <span class="dot" aria-hidden="true"></span>
                Private rolodex
            </p>

            <h1 class="rise mt-6 text-[2.6rem] font-bold leading-[1.02] tracking-tight sm:text-6xl" style="animation-delay: 80ms">
                Never scramble for a
                <span class="gradient-text">seventh</span>
                again.
            </h1>

            <p class="rise mt-5 max-w-xl text-base leading-relaxed text-white/70 sm:text-lg" style="animation-delay: 160ms">
                Everyone you can call to fill a court — who sets, who hits, which side of the net,
                who is under six feet — with phone numbers, notes and a photo. Find them in a second.
            </p>

            <div class="rise mt-8 flex flex-col gap-3 sm:flex-row sm:items-center" style="animation-delay: 240ms">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="cta-primary"
                >
                    <i class="pi pi-plus-circle" aria-hidden="true" />
                    Create your rolodex
                </Link>

                <Link
                    v-if="canLogin"
                    :href="signedIn ? route('people.index') : route('login')"
                    class="cta-ghost"
                >
                    {{ signedIn ? 'Open your rolodex' : 'Log in' }}
                </Link>
            </div>

            <p class="rise mt-6 text-xs text-white/40" style="animation-delay: 320ms">
                Your list is private to your account. Nobody else can see it.
            </p>
        </section>

        <!-- the tag strip -->
        <section class="relative z-10 py-5">
            <div class="marquee">
                <div class="marquee-track">
                    <span v-for="(tag, i) in [...tags, ...tags]" :key="`${tag}-${i}`" class="pill">
                        {{ tag }}
                    </span>
                </div>
            </div>
        </section>

        <!-- the cards -->
        <section class="relative z-10 mx-auto w-full max-w-5xl px-5 py-16 sm:py-24">
            <h2
                data-reveal
                class="reveal max-w-lg text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                A card for everyone you would text at 6pm on a Wednesday.
            </h2>

            <ul class="mt-10 grid gap-4 sm:grid-cols-3">
                <li
                    v-for="(person, i) in roster"
                    :key="person.name"
                    data-reveal
                    class="reveal player-card"
                    :class="`tint-${person.tint}`"
                    :style="{ transitionDelay: `${i * 120}ms` }"
                >
                    <div class="flex items-center gap-3">
                        <span class="avatar" aria-hidden="true">
                            {{ person.name.split(' ').map((p) => p[0]).join('') }}
                        </span>
                        <span class="font-semibold">{{ person.name }}</span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <span v-for="tag in person.tags" :key="tag" class="chip">{{ tag }}</span>
                    </div>

                    <div class="mt-5 flex items-center gap-2 text-xs text-white/50">
                        <i class="pi pi-phone" aria-hidden="true" />
                        Tap to call
                    </div>
                </li>
            </ul>
        </section>

        <!-- what it does -->
        <section class="relative z-10 mx-auto w-full max-w-5xl px-5 pb-16 sm:pb-24">
            <ul class="grid gap-4 sm:grid-cols-2">
                <li
                    v-for="(feature, i) in features"
                    :key="feature.title"
                    data-reveal
                    class="reveal feature"
                    :style="{ transitionDelay: `${i * 90}ms` }"
                >
                    <i :class="feature.icon" class="feature-icon" aria-hidden="true" />
                    <h3 class="mt-4 text-lg font-semibold">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/60">{{ feature.body }}</p>
                </li>
            </ul>
        </section>

        <!-- closing -->
        <section class="relative z-10 mx-auto w-full max-w-5xl px-5 pb-20">
            <div data-reveal class="reveal closing">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Ready when you need a seventh.
                    </h2>
                    <p class="mt-2 text-sm text-white/60">
                        Set it up once. Then it is just a search.
                    </p>
                </div>

                <Link v-if="canRegister" :href="route('register')" class="cta-primary shrink-0">
                    Get started
                </Link>
            </div>
        </section>

        <footer class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-xs text-white/40">
                <span>Volleyball Rolodex</span>
                <Link
                    v-if="canLogin"
                    :href="signedIn ? route('people.index') : route('login')"
                    class="transition-colors hover:text-white/70"
                >
                    {{ signedIn ? 'Your rolodex' : 'Log in' }}
                </Link>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.court {
    background:
        radial-gradient(120% 80% at 50% -10%, #1b2a5e 0%, transparent 60%),
        radial-gradient(90% 60% at 90% 10%, rgba(242, 55, 161, 0.18) 0%, transparent 55%),
        linear-gradient(180deg, #080d1c 0%, #0b1121 55%, #080d1c 100%);
}

/* The glow drifts slowly, so the page never looks completely still. */
.glow {
    position: fixed;
    inset: -20% -20% auto -20%;
    height: 70vh;
    background: radial-gradient(closest-side, rgba(91, 111, 224, 0.22), transparent);
    filter: blur(30px);
    animation: drift 18s ease-in-out infinite alternate;
    pointer-events: none;
}

.ball-mark svg {
    width: 1.5rem;
    height: 1.5rem;
    color: #f237a1;
}

.ball {
    position: absolute;
    top: -1rem;
    right: -3rem;
    width: 15rem;
    height: 15rem;
    color: rgba(255, 255, 255, 0.13);
    animation: bob 7s ease-in-out infinite;
    pointer-events: none;
}

.ball svg {
    width: 100%;
    height: 100%;
    animation: spin 40s linear infinite;
}

.dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 999px;
    background: #f237a1;
    box-shadow: 0 0 0 0 rgba(242, 55, 161, 0.7);
    animation: ping 2.4s ease-out infinite;
}

.gradient-text {
    background: linear-gradient(92deg, #f237a1 0%, #ff8ac6 45%, #8ea2ff 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* --- buttons ------------------------------------------------------------ */

.cta-primary,
.cta-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    border-radius: 0.85rem;
    padding: 0.95rem 1.5rem;
    font-weight: 600;
    font-size: 0.975rem;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        background-color 0.18s ease;
}

.cta-primary {
    background: linear-gradient(120deg, #f237a1, #ff5fb4);
    color: #16021f;
    box-shadow: 0 12px 30px -10px rgba(242, 55, 161, 0.85);
    animation: breathe 3.6s ease-in-out infinite;
}

.cta-primary:hover,
.cta-primary:focus-visible {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 18px 38px -10px rgba(242, 55, 161, 0.95);
}

.cta-ghost {
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.04);
    color: #fff;
}

.cta-ghost:hover,
.cta-ghost:focus-visible {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

/* --- marquee ------------------------------------------------------------ */

.marquee {
    overflow: hidden;
    mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
}

.marquee-track {
    display: flex;
    width: max-content;
    gap: 0.6rem;
    animation: slide 34s linear infinite;
}

.pill {
    white-space: nowrap;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    padding: 0.5rem 0.9rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.72);
}

/* --- cards -------------------------------------------------------------- */

.player-card,
.feature,
.closing {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.035);
    padding: 1.15rem;
    backdrop-filter: blur(6px);
}

.player-card {
    animation: bob 9s ease-in-out infinite;
}

.player-card.tint-pink {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 20px 40px -28px rgba(242, 55, 161, 0.9);
}

.player-card.tint-blue {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 20px 40px -28px rgba(91, 111, 224, 0.9);
    animation-duration: 11s;
    animation-delay: 0.6s;
}

.player-card.tint-pink:nth-child(3) {
    animation-duration: 10s;
    animation-delay: 1.1s;
}

.avatar {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.7rem;
    background: rgba(242, 55, 161, 0.18);
    font-size: 0.8rem;
    font-weight: 700;
    color: #ffd0ea;
}

.chip {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.07);
    padding: 0.2rem 0.55rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.72);
}

.feature-icon {
    display: grid;
    place-items: center;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.7rem;
    background: rgba(91, 111, 224, 0.2);
    color: #b9c4ff;
}

.closing {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(242, 55, 161, 0.16), transparent 60%),
        rgba(255, 255, 255, 0.035);
}

/* --- motion ------------------------------------------------------------- */

.rise {
    animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition:
        opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}

.reveal.is-in {
    opacity: 1;
    transform: none;
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-9px);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes drift {
    from {
        transform: translate3d(-6%, 0, 0) scale(1);
    }
    to {
        transform: translate3d(6%, 4%, 0) scale(1.15);
    }
}

@keyframes slide {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}

@keyframes breathe {
    0%,
    100% {
        box-shadow: 0 12px 30px -10px rgba(242, 55, 161, 0.85);
    }
    50% {
        box-shadow: 0 16px 40px -8px rgba(242, 55, 161, 1);
    }
}

@keyframes ping {
    0% {
        box-shadow: 0 0 0 0 rgba(242, 55, 161, 0.7);
    }
    70% {
        box-shadow: 0 0 0 0.6rem rgba(242, 55, 161, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(242, 55, 161, 0);
    }
}

/* The ball is decoration; on a narrow screen it moves out of the way. */
@media (max-width: 640px) {
    .ball {
        top: -2.5rem;
        right: -6rem;
        width: 12rem;
        height: 12rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .glow,
    .ball,
    .ball svg,
    .dot,
    .player-card,
    .cta-primary,
    .marquee-track,
    .rise {
        animation: none !important;
    }

    .reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
