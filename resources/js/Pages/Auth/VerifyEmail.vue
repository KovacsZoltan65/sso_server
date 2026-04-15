<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({});
</script>

<template>
    <Head :title="trans('auth.verify_email.page_title')" />

    <GuestLayout
        :title="trans('auth.verify_email.title')"
        :description="trans('auth.verify_email.description')"
    >
        <Message v-if="props.status === 'verification-link-sent'" severity="success" class="mb-5">
            {{ trans('auth.verify_email.link_sent') }}
        </Message>

        <div class="space-y-5">
            <p class="section-copy">
                {{ trans('auth.verify_email.description') }}
            </p>

            <Button
                :label="trans('auth.verify_email.resend_cta')"
                icon="pi pi-send"
                class="w-full justify-center"
                :loading="form.processing"
                @click="form.post(route('verification.send'))"
            />

            <Link :href="route('logout')" method="post" as="button" class="w-full text-center text-sm font-semibold text-slate-600">
                {{ trans('common.logout') }}
            </Link>
        </div>
    </GuestLayout>
</template>
