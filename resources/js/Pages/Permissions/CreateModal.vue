<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import PermissionFormFields from '@/Pages/Permissions/Partials/PermissionFormFields.vue';

defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['saved', 'update:visible']);

const form = useForm({
    name: '',
});

const close = () => {
    if (form.processing) {
        return;
    }

    form.reset();
    form.clearErrors();
    emit('update:visible', false);
};

const submit = () => {
    form.post(route('admin.permissions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', {
                message: 'Permission created successfully.',
                type: 'create',
            });
            close();
        },
    });
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        header="Create Permission"
        :style="{ width: '32rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <form class="grid gap-6" @submit.prevent="submit">
            <PermissionFormFields
                :form="form"
                :disabled="form.processing"
            />

            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    text
                    :disabled="form.processing"
                    @click="close"
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
    </Dialog>
</template>
