<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <GuestLayout
        title="Create an operator account"
        description="Breeze registration remains available during foundation setup. Roles can be assigned later by an administrator."
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">Name</label>
                <InputText v-model="form.name" class="w-full" autocomplete="name" />
                <small v-if="form.errors.name" class="text-red-600">{{ form.errors.name }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" type="email" autocomplete="username" />
                <small v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">Password</label>
                <Password v-model="form.password" class="w-full" input-class="w-full" :feedback="false" toggle-mask />
                <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">Confirm password</label>
                <Password
                    v-model="form.password_confirmation"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                />
            </div>

            <Button type="submit" label="Register" icon="pi pi-user-plus" class="w-full justify-center" :loading="form.processing" />

            <div class="text-sm text-slate-600">
                Already have an account?
                <Link :href="route('login')" class="font-semibold text-sky-700">Log in</Link>
            </div>
        </form>
    </GuestLayout>
</template>
