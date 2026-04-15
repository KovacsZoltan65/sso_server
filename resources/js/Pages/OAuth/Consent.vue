<script setup>
import PublicAuthLayout from '@/Layouts/PublicAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
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

const outcomeCopy = {
    approve: props.client.originHost
        ? trans('pages.consent.outcome_approve_with_host', { host: `${props.client.originHost}${props.client.returnPath || ''}` })
        : trans('pages.consent.outcome_approve_generic'),
    deny: props.client.originHost
        ? trans('pages.consent.outcome_deny_with_host', { host: props.client.originHost })
        : trans('pages.consent.outcome_deny_generic'),
};

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
    <Head :title="trans('pages.consent.title')" />

    <PublicAuthLayout :title="trans('pages.consent.title')" :description="summary.description">
        <div class="space-y-6">
            <p
                v-if="approveForm.errors.consent_token || denyForm.errors.consent_token"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ approveForm.errors.consent_token || denyForm.errors.consent_token }}
            </p>

            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ trans('fields.application') }}</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ client.name }}</h3>
                    </div>
                    <span
                        class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                    >
                        {{ client.trustLabel }}
                    </span>
                </div>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ client.description }}</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ client.trustDescription }}</p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ trans('pages.consent.registered_origin') }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">
                            {{ client.originHost || trans('pages.consent.registered_application') }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ trans('pages.consent.return_destination') }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">
                            {{ client.originHost || trans('pages.consent.application_callback') }}{{ client.returnPath || '' }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ trans('pages.consent.requested_access') }}</p>
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
                    <p class="mb-2 text-xs leading-5 text-slate-500">{{ outcomeCopy.approve }}</p>
                    <Button
                        type="submit"
                        :label="trans('actions.approve')"
                        icon="pi pi-check"
                        class="w-full justify-center"
                        :loading="approveForm.processing"
                    />
                </form>
                <form @submit.prevent="submitDeny">
                    <p class="mb-2 text-xs leading-5 text-slate-500">{{ outcomeCopy.deny }}</p>
                    <Button
                        type="submit"
                        :label="trans('actions.deny')"
                        icon="pi pi-times"
                        severity="secondary"
                        class="w-full justify-center"
                        :loading="denyForm.processing"
                    />
                </form>
            </div>

            <p class="text-xs leading-6 text-slate-500">
                {{ trans('pages.consent.footer_note') }}
            </p>
        </div>
    </PublicAuthLayout>
</template>
