<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Button from 'primevue/button';
import Password from 'primevue/password';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="trans('auth.confirm_password_page.page_title')" />

    <GuestLayout
        :title="trans('auth.confirm_password_page.title')"
        :description="trans('auth.confirm_password_page.description')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700">{{ trans('auth.password') }}</label>
                <Password v-model="form.password" class="w-full" input-class="w-full" :feedback="false" toggle-mask />
                <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <Button
                type="submit"
                :label="trans('auth.confirm_password_page.submit')"
                icon="pi pi-lock"
                class="w-full justify-center"
                :loading="form.processing"
            />
        </form>
    </GuestLayout>
</template>
