<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientUserAccessFormFields from '@/Pages/ClientUserAccess/components/ClientUserAccessFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    clientOptions: {
        type: Array,
        default: () => [],
    },
    userOptions: {
        type: Array,
        default: () => [],
    },
});

const formId = 'client-user-access-create-form';

const form = useForm({
    client_id: null,
    user_id: null,
    is_active: true,
    allowed_from: '',
    allowed_until: '',
    notes: '',
});

const submit = () => {
    form.post(route('admin.client-user-access.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.client-user-access.index'));
};
</script>

<template>
    <Head title="Create Client Access" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Create Client Access"
                description="Assign an authenticated user to a specific SSO client with optional activation windows and notes."
            />

            <div class="admin-form-shell overflow-y-auto pr-1">
                <div class="flex flex-col">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Access Assignment</div>
                                <p class="text-sm text-slate-500">
                                    Create an explicit allow-list entry for a user and client pair.
                                </p>
                            </div>
                        </template>

                        <form :id="formId" class="flex min-h-0 flex-col gap-6" @submit.prevent="submit">
                            <ClientUserAccessFormFields
                                :form="form"
                                :clientOptions="clientOptions"
                                :userOptions="userOptions"
                                :disabled="form.processing"
                            />
                        </form>

                        <template #footer>
                            <div class="flex flex-wrap justify-end gap-3">
                                <Button
                                    type="button"
                                    label="Cancel"
                                    severity="secondary"
                                    outlined
                                    :disabled="form.processing"
                                    @click="cancel"
                                />
                                <Button
                                    type="submit"
                                    :form="formId"
                                    label="Create Access"
                                    icon="pi pi-check"
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                />
                            </div>
                        </template>
                    </AdminFormCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
