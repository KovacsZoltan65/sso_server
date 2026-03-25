<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScopeForm from '@/Pages/Scopes/Partials/ScopeForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    scope: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.scope.name ?? '',
    code: props.scope.code ?? '',
    description: props.scope.description ?? '',
    is_active: props.scope.isActive ?? true,
});

const submit = () => {
    form.put(route('admin.scopes.update', props.scope.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.scopes.index'));
};
</script>

<template>
    <Head title="Edit Scope" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Edit Scope"
                description="Update the scope catalog entry without losing the fixed action bar or form context."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Scope Details</div>
                            <p class="text-sm text-slate-500">
                                Update the display metadata or active state. Code changes are blocked while clients still use the scope.
                            </p>
                        </div>
                    </template>

                    <ScopeForm
                        :form="form"
                        mode="edit"
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
                                label="Save Changes"
                                icon="pi pi-save"
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
