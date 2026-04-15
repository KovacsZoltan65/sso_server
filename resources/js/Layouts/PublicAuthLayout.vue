<script setup>
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
    languageSwitcherOffsetX: {
        type: Number,
        default: 0,
    },
    languageSwitcherOffsetY: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div class="min-h-screen bg-slate-100 px-4 py-10 sm:px-6 lg:px-8">
        <div
            class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-5xl items-center justify-center"
        >
            <div
                class="grid w-full overflow-hidden rounded-[2rem] bg-white shadow-xl shadow-slate-200/80 lg:grid-cols-[1fr_0.92fr]"
            >
                <section
                    class="hidden bg-slate-950 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between"
                >
                    <div class="inline-flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-sky-200"
                        >
                            <i class="pi pi-shield text-xl"></i>
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-200/80"
                            >
                                {{ $t("public_auth_layout.hero.badge") }}
                            </p>
                            <p class="mt-1 text-lg font-semibold text-white">
                                {{ $t("public_auth_layout.hero.title") }}
                            </p>
                        </div>
                    </div>

                    <div class="max-w-md">
                        <h1 class="text-4xl font-semibold leading-tight text-white">
                            {{ $t("public_auth_layout.hero.heading") }}
                        </h1>
                        <p class="mt-4 text-base leading-7 text-slate-300">
                            {{ $t("public_auth_layout.hero.description") }}
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-200/80"
                            >
                                {{ $t("public_auth_layout.card.title") }}
                            </p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                {{ $t("public_auth_layout.card.description") }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="relative flex items-center px-6 py-10 sm:px-10 lg:px-12">
                    <div
                        class="absolute"
                        :style="{
                            right: `${languageSwitcherOffsetX}px`,
                            top: `${languageSwitcherOffsetY}px`,
                        }"
                    >
                        <LanguageSwitcher />
                    </div>
                    <div class="w-full">
                        <div class="mb-8">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"
                            >
                                {{ $t("public_auth_layout.form.eyebrow") }}
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold tracking-tight text-slate-950"
                            >
                                {{ title }}
                            </h2>
                            <p
                                v-if="description"
                                class="mt-3 text-sm leading-7 text-slate-600"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
