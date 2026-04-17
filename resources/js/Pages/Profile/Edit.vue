<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { trans } from 'laravel-vue-i18n';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import Toast from 'primevue/toast';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head :title="trans('profile.title')" />

    <AuthenticatedLayout>
        <Toast />

        <div class="admin-form-page">
            <PageHeader
                :title="trans('profile.title')"
                :description="trans('profile.description')"
            />

            <div class="admin-form-shell space-y-6 overflow-y-auto pr-1">
                <div class="grid gap-6 lg:grid-cols-2">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">{{ trans('profile.identity_title') }}</div>
                                <p class="text-sm text-slate-500">
                                    {{ trans('profile.identity_description') }}
                                </p>
                            </div>
                        </template>

                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </AdminFormCard>

                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">{{ trans('profile.password_title') }}</div>
                                <p class="text-sm text-slate-500">
                                    {{ trans('profile.password_description') }}
                                </p>
                            </div>
                        </template>

                        <UpdatePasswordForm />
                    </AdminFormCard>
                </div>

                <AdminFormCard :grow="false">
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">{{ trans('profile.delete_title') }}</div>
                            <p class="text-sm text-slate-500">
                                {{ trans('profile.delete_description') }}
                            </p>
                        </div>
                    </template>

                    <DeleteUserForm />
                </AdminFormCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
