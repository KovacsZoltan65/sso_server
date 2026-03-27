<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
});

const formId = 'client-create-form';

const form = useForm({
    name: '',
    redirect_uris: [''],
    scopes: [],
    is_active: true,
    token_policy_id: null,
});

const submit = () => {
    form.post(route('admin.sso-clients.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.sso-clients.index'));
};
</script>

<template>
    <Head title="Create Client" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Create SSO Client"
                description="Register a new client with redirect URIs, scopes, and activity state in the same admin form shell used for complex edit screens."
            />

            <div class="admin-form-shell overflow-y-auto pr-1">
                <div class="flex flex-col">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Client Configuration</div>
                                <p class="text-sm text-slate-500">
                                    The client ID and client secret are generated automatically during creation.
                                </p>
                            </div>
                        </template>

                        <ClientForm
                            :formId="formId"
                            :form="form"
                            mode="create"
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
                                    form="client-create-form"
                                    label="Create Client"
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
