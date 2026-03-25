<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScopeForm from '@/Pages/Scopes/Partials/ScopeForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const form = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(route('admin.scopes.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.scopes.index'));
};
</script>

<template>
    <Head title="Create Scope" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Create Scope"
                description="Define a new reusable scope entry for clients and future consent-aware flows."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Scope Details</div>
                            <p class="text-sm text-slate-500">
                                Scope codes are the technical identifiers used by clients. Keep them stable and review-friendly.
                            </p>
                        </div>
                    </template>

                    <ScopeForm
                        :form="form"
                        mode="create"
                        :loading="form.processing"
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
                                label="Create Scope"
                                icon="pi pi-check"
                                :loading="form.processing"
                                :disabled="form.processing"
                            />
                        </div>
                    </template>
                </AdminFormCard>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
