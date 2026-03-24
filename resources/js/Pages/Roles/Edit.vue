<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const props = defineProps({
    role: {
        type: Object,
        required: true,
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
        <PageHeader
            title="Edit Role"
            description="Update role details with the same card-based admin form flow used elsewhere in the module."
        />

        <Card class="surface-card">
            <template #content>
                <form class="grid gap-6" @submit.prevent="submit">
                    <RoleFormFields
                        :form="form"
                        :permissionOptions="permissionOptions"
                        :disabled="form.processing"
                    />

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
                </form>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
