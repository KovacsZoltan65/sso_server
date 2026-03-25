<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TokenPolicyForm from '@/Pages/TokenPolicies/Partials/TokenPolicyForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const form = useForm({
    name: '',
    code: '',
    description: '',
    access_token_ttl_minutes: 60,
    refresh_token_ttl_minutes: 43200,
    refresh_token_rotation_enabled: true,
    pkce_required: false,
    reuse_refresh_token_forbidden: true,
    is_default: false,
    is_active: true,
});

const submit = () => {
    form.post(route('admin.token-policies.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.token-policies.index'));
};
</script>

<template>
    <Head title="Create Token Policy" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Create Token Policy"
                description="Define a reusable token issuance policy for client registrations and future grant enforcement."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <TokenPolicyForm :form="form" mode="create" :loading="form.processing" />

                    <template #footer>
                        <div class="flex flex-wrap justify-end gap-3">
                            <Button type="button" label="Cancel" severity="secondary" text :disabled="form.processing" @click="cancel" />
                            <Button type="submit" label="Save" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" />
                        </div>
                    </template>
                </AdminFormCard>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
