<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientUserAccessFormFields from '@/Pages/ClientUserAccess/components/ClientUserAccessFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    access: {
        type: Object,
        required: true,
    },
    clientOptions: {
        type: Array,
        default: () => [],
    },
    userOptions: {
        type: Array,
        default: () => [],
    },
});

const toLocalDateTime = (value) => {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 16);
};

const formId = 'client-user-access-edit-form';

const form = useForm({
    client_id: props.access.clientId ?? null,
    user_id: props.access.userId ?? null,
    is_active: props.access.isActive ?? true,
    allowed_from: toLocalDateTime(props.access.allowedFrom),
    allowed_until: toLocalDateTime(props.access.allowedUntil),
    notes: props.access.notes ?? '',
});

const submit = () => {
    form.put(route('admin.client-user-access.update', props.access.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.client-user-access.index'));
};
</script>

<template>
    <Head title="Edit Client Access" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Edit Client Access"
                description="Update access status, time windows, and notes for an existing client-to-user assignment."
            />

            <div class="admin-form-shell overflow-y-auto pr-1">
                <div class="flex flex-col">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Access Assignment</div>
                                <p class="text-sm text-slate-500">
                                    Review the selected client and user pair, then adjust availability or activation state.
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
                                    label="Save Changes"
                                    icon="pi pi-save"
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
