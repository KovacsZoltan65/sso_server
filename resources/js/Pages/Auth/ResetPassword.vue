<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="trans('auth.reset_password_page.page_title')" />

    <GuestLayout
        :title="trans('auth.reset_password_page.title')"
        :description="trans('auth.reset_password_page.title')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">{{ trans('auth.email') }}</label>
                <InputText v-model="form.email" class="w-full" type="email" autocomplete="username" />
                <small v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">{{ trans('auth.password') }}</label>
                <Password v-model="form.password" class="w-full" input-class="w-full" :feedback="false" toggle-mask />
                <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">{{ trans('profile.confirm_password') }}</label>
                <Password
                    v-model="form.password_confirmation"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                />
            </div>

            <Button
                type="submit"
                :label="trans('auth.reset_password_page.submit')"
                icon="pi pi-check"
                class="w-full justify-center"
                :loading="form.processing"
            />
        </form>
    </GuestLayout>
</template>
