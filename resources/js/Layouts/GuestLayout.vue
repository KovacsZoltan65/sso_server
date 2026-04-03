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
                    <div class="text-xs uppercase tracking-[0.35em] text-sky-200/80">Secure Access</div>
                    <div class="text-2xl font-semibold">Account sign in</div>
                </div>
            </div>

            <div class="max-w-xl">
                <div class="eyebrow !text-sky-200/90">Authentication</div>
                <h1 class="mt-4 text-5xl font-semibold leading-tight">
                    Sign in to continue securely.
                </h1>
                <p class="mt-6 text-lg leading-8 text-slate-300">
                    Your account is protected through the central authentication service.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Access</div>
                    <div class="mt-3 text-sm text-slate-300">Only authorized accounts can continue beyond this screen.</div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Security</div>
                    <div class="mt-3 text-sm text-slate-300">Authentication events are handled with the same security standards as the rest of the platform.</div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="text-xs uppercase tracking-[0.24em] text-sky-200/80">Trust</div>
                    <div class="mt-3 text-sm text-slate-300">Use your existing account details to sign in safely and continue.</div>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div class="w-full max-w-xl">
                <div class="surface-card px-6 py-8 sm:px-10 sm:py-10">
                    <div class="eyebrow">Sign in</div>
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
