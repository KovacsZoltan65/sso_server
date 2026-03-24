<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const props = defineProps({
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
        <PageHeader
            title="Create Role"
            description="Create a new role using the same admin form rhythm as the Users module."
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
                            label="Create Role"
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
