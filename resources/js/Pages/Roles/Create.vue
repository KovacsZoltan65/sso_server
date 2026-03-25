<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    guardName: {
        type: String,
        default: 'web',
    },
    permissionOptions: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    permissions: [],
});

const submit = () => {
    form.post(route('admin.roles.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.roles.index'));
};
</script>

<template>
    <Head title="Create Role" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Create Role"
                description="Create a new role using the same admin form rhythm as the Users module."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Role Details</div>
                            <p class="text-sm text-slate-500">
                                Configure the role name and assign permissions without leaving the page flow.
                            </p>
                        </div>
                    </template>

                    <RoleFormFields
                        :form="form"
                        :guardName="guardName"
                        :permissionOptions="permissionOptions"
                        :disabled="form.processing"
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
                                label="Create Role"
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
