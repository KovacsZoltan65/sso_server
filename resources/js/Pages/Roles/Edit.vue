<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    role: {
        type: Object,
        required: true,
    },
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
    name: props.role.name ?? '',
    permissions: [...(props.role.permissions ?? [])],
});

const submit = () => {
    form.put(route('admin.roles.update', props.role.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.roles.index'));
};
</script>

<template>
    <Head title="Edit Role" />

    <AuthenticatedLayout>
        <div class="admin-form-page">
            <PageHeader
                title="Edit Role"
                description="Update role details with the same card-based admin form flow used elsewhere in the module."
            />

            <form class="admin-form-shell" @submit.prevent="submit">
                <AdminFormCard>
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Role Details</div>
                            <p class="text-sm text-slate-500">
                                Adjust the role name and permission coverage while keeping the action bar visible.
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
