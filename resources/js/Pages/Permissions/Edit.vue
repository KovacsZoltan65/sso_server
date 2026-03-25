<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PermissionFormFields from '@/Pages/Permissions/Partials/PermissionFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const props = defineProps({
    permission: {
        type: Object,
        required: true,
    },
    guardName: {
        type: String,
        default: 'web',
    },
});

const form = useForm({
    name: props.permission.name ?? '',
});

const submit = () => {
    form.put(route('admin.permissions.update', props.permission.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.permissions.index'));
};
</script>

<template>
    <Head title="Edit Permission" />

    <AuthenticatedLayout>
        <PageHeader
            title="Edit Permission"
            description="Update permission details with the same card-based admin form flow used elsewhere in the module."
        />

        <Card class="surface-card">
            <template #content>
                <form class="grid gap-6" @submit.prevent="submit">
                    <PermissionFormFields
                        :form="form"
                        :guardName="guardName"
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
