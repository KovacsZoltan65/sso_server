<script setup>
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
import { trans } from "laravel-vue-i18n";

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="auth-grid">
        <section
            class="auth-hero hidden px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-3xl bg-white/10 text-2xl text-sky-200"
                >
                    <i class="pi pi-shield"></i>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-sky-200/80">
                        {{ trans("guest_layout.hero.badge") }}
                    </div>
                    <div class="text-2xl font-semibold">
                        {{ trans("guest_layout.hero.title") }}
                    </div>
                </div>
            </div>

            <div class="max-w-xl">
                <div class="eyebrow !text-sky-200/90">
                    {{ trans("guest_layout.hero.eyebrow") }}
                </div>
                <h1 class="mt-4 text-5xl font-semibold leading-tight">
                    {{ trans("guest_layout.hero.heading") }}
                </h1>
                <p class="mt-6 text-lg leading-8 text-slate-300">
                    {{ trans("guest_layout.hero.description") }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">
                        {{ trans("guest_layout.cards.access.title") }}
                    </div>
                    <div class="mt-3 text-sm text-slate-300">
                        {{ trans("guest_layout.cards.access.description") }}
                    </div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">
                        {{ trans("guest_layout.cards.security.title") }}
                    </div>
                    <div class="mt-3 text-sm text-slate-300">
                        {{ trans("guest_layout.cards.security.description") }}
                    </div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">
                        {{ trans("guest_layout.cards.trust.title") }}
                    </div>
                    <div class="mt-3 text-sm text-slate-300">
                        {{ trans("guest_layout.cards.trust.description") }}
                    </div>
                </div>
            </div>
        </section>

        <section
            class="relative flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12"
        >
            <div class="absolute right-0 top-0">
                <LanguageSwitcher />
            </div>
            <div class="w-full max-w-xl">
                <div class="surface-card px-6 py-8 sm:px-10 sm:py-10">
                    <div class="eyebrow">{{ trans("auth.login.title") }}</div>
                    <h2 class="section-title mt-2">{{ title }}</h2>
                    <p v-if="description" class="section-copy mt-2">{{ description }}</p>

                    <div class="mt-8">
                        <slot />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
