<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    <Head title="Verify email" />

    <GuestLayout
        title="Verify your email address"
        description="Email verification is still available from the Breeze stack if you enable it on the User model later."
    >
        <Message v-if="props.status === 'verification-link-sent'" severity="success" class="mb-5">
            A fresh verification link has been sent to your email address.
        </Message>

        <div class="space-y-5">
            <p class="section-copy">
                Thanks for signing up. Before getting started, confirm your address by clicking the link we emailed to you.
            </p>

            <Button
                label="Resend verification email"
                icon="pi pi-send"
                class="w-full justify-center"
                :loading="form.processing"
                @click="form.post(route('verification.send'))"
            />

            <Link :href="route('logout')" method="post" as="button" class="w-full text-center text-sm font-semibold text-slate-600">
                Log out
            </Link>
        </div>
    </GuestLayout>
</template>
