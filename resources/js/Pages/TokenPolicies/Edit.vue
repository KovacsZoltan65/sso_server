<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TokenPolicyForm from '@/Pages/TokenPolicies/Partials/TokenPolicyForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    tokenPolicy: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.tokenPolicy.name ?? '',
    code: props.tokenPolicy.code ?? '',
    description: props.tokenPolicy.description ?? '',
    access_token_ttl_minutes: props.tokenPolicy.access_token_ttl_minutes ?? 60,
    refresh_token_ttl_minutes: props.tokenPolicy.refresh_token_ttl_minutes ?? 43200,
    refresh_token_rotation_enabled: props.tokenPolicy.refresh_token_rotation_enabled ?? true,
    pkce_required: props.tokenPolicy.pkce_required ?? false,
    reuse_refresh_token_forbidden: props.tokenPolicy.reuse_refresh_token_forbidden ?? true,
    is_default: props.tokenPolicy.is_default ?? false,
    is_active: props.tokenPolicy.is_active ?? true,
});

const submit = () => {
    form.put(route('admin.token-policies.update', props.tokenPolicy.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.token-policies.index'));
};
</script>

<template>
    <Head :title="`Edit ${tokenPolicy.name}`" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Edit Token Policy"
                description="Adjust TTLs, rotation, PKCE, and default behavior without leaving the policy management flow."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <TokenPolicyForm :form="form" mode="edit" :loading="form.processing" />

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
