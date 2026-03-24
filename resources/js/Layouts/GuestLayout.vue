<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="auth-grid">
        <section class="auth-hero hidden px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-white/10 text-2xl text-sky-200">
                    <i class="pi pi-shield"></i>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-sky-200/80">Central Auth</div>
                    <div class="text-2xl font-semibold">sso_server</div>
                </div>
            </div>

            <div class="max-w-xl">
                <div class="eyebrow !text-sky-200/90">Foundation</div>
                <h1 class="mt-4 text-5xl font-semibold leading-tight">
                    Secure the identity edge before the SSO modules land.
                </h1>
                <p class="mt-6 text-lg leading-8 text-slate-300">
                    Laravel 13, Inertia, Vue 3, PrimeVue, permissions, repositories, DTOs, and audit logging are ready for controlled expansion.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Roles</div>
                    <div class="mt-3 text-sm text-slate-300">Seeded `superadmin` and `admin` access baselines.</div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Audit</div>
                    <div class="mt-3 text-sm text-slate-300">Login, logout, and profile events are ready to log.</div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Scale</div>
                    <div class="mt-3 text-sm text-slate-300">Modular routes and placeholders prepared for SSO domain expansion.</div>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div class="w-full max-w-xl">
                <div class="surface-card px-6 py-8 sm:px-10 sm:py-10">
                    <div class="eyebrow">Authentication</div>
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
