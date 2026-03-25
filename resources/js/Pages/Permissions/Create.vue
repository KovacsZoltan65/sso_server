<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PermissionFormFields from '@/Pages/Permissions/Partials/PermissionFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const props = defineProps({
    guardName: {
        type: String,
        default: 'web',
    },
});

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('admin.permissions.store'), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.permissions.index'));
};
</script>

<template>
    <Head title="Create Permission" />

    <AuthenticatedLayout>
        <PageHeader
            title="Create Permission"
            description="Create a new permission using the same admin form rhythm as the Users module."
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
                            label="Create Permission"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
