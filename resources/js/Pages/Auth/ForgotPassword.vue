<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot password" />

    <GuestLayout
        title="Reset your password"
        description="Request a reset link for your operator account. Mail transport is left environment-configurable."
    >
        <Message v-if="status" severity="success" class="mb-5">{{ status }}</Message>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" type="email" autocomplete="username" />
                <small v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <Button type="submit" label="Email reset link" icon="pi pi-send" class="w-full justify-center" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
