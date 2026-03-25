<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
});

const formId = 'client-edit-form';

const form = useForm({
    name: props.client.name ?? '',
    client_id: props.client.clientId ?? '',
    redirect_uris: [...(props.client.redirectUris ?? [''])],
    scopes: [...(props.client.scopes ?? [])],
    is_active: props.client.isActive ?? true,
    token_policy_id: props.client.tokenPolicyId ?? null,
});

const submit = () => {
    form.put(route('admin.sso-clients.update', props.client.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.sso-clients.index'));
};
</script>

<template>
    <Head title="Edit Client" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Edit SSO Client"
                description="Update redirect targets, scopes, and activation state without exposing the client secret again."
            />

            <div class="admin-form-shell">
                <AdminFormCard>
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Client Configuration</div>
                            <p class="text-sm text-slate-500">
                                The client secret is never returned again after creation. Use a dedicated rotate flow later for secret changes.
                            </p>
                        </div>
                    </template>

                    <ClientForm
                        :formId="formId"
                        :form="form"
                        mode="edit"
                        :loading="form.processing"
                        :scopeOptions="scopeOptions"
                        :tokenPolicies="tokenPolicies"
                        @submit="submit"
                    />

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
                                form="client-edit-form"
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
    </AuthenticatedLayout>
</template>
