<script setup>
import PublicAuthLayout from '@/Layouts/PublicAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    consentToken: {
        type: String,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    scopes: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
});

const approveForm = useForm({
    consent_token: props.consentToken,
});

const denyForm = useForm({
    consent_token: props.consentToken,
});

const submitApprove = () => {
    approveForm.post(route('oauth.authorize.approve'));
};

const submitDeny = () => {
    denyForm.post(route('oauth.authorize.deny'));
};
</script>

<template>
    <Head title="Review access request" />

    <PublicAuthLayout title="Review access request" :description="summary.description">
        <div class="space-y-6">
            <p
                v-if="approveForm.errors.consent_token || denyForm.errors.consent_token"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ approveForm.errors.consent_token || denyForm.errors.consent_token }}
            </p>

            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Application</p>
                <h3 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ client.name }}</h3>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ client.description }}</p>
            </section>

            <section class="space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Requested access</p>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ summary.title }}</p>
                </div>

                <ul class="space-y-3">
                    <li
                        v-for="scope in scopes"
                        :key="scope.code"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm shadow-slate-100/80"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ scope.name }}</p>
                                <p v-if="scope.description" class="mt-1 text-sm leading-6 text-slate-600">
                                    {{ scope.description }}
                                </p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">
                                {{ scope.code }}
                            </span>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="grid gap-3 sm:grid-cols-2">
                <form @submit.prevent="submitApprove">
                    <Button
                        type="submit"
                        label="Approve"
                        icon="pi pi-check"
                        class="w-full justify-center"
                        :loading="approveForm.processing"
                    />
                </form>
                <form @submit.prevent="submitDeny">
                    <Button
                        type="submit"
                        label="Deny"
                        icon="pi pi-times"
                        severity="secondary"
                        class="w-full justify-center"
                        :loading="denyForm.processing"
                    />
                </form>
            </div>

            <p class="text-xs leading-6 text-slate-500">
                Both decisions rely on the server-side consent token instead of browser-provided authorize fields.
            </p>
        </div>
    </PublicAuthLayout>
</template>
