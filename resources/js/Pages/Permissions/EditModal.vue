<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import PermissionFormFields from '@/Pages/Permissions/Partials/PermissionFormFields.vue';
import { watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    permission: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['saved', 'update:visible']);

const form = useForm({
    name: '',
});

const fillForm = () => {
    form.defaults({
        name: props.permission?.name ?? '',
    });
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.visible, props.permission],
    ([visible, permission]) => {
        if (visible && permission) {
            fillForm();
        }
    },
    { immediate: true },
);

const close = () => {
    if (form.processing) {
        return;
    }

    form.clearErrors();
    emit('update:visible', false);
};

const submit = () => {
    if (!props.permission) {
        return;
    }

    form.put(route('admin.permissions.update', props.permission.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', {
                message: 'Permission updated successfully.',
                type: 'edit',
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
        header="Edit Permission"
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
                    label="Save Changes"
                    icon="pi pi-save"
                    :loading="form.processing"
                    :disabled="form.processing || !permission"
                />
            </div>
        </form>
    </Dialog>
</template>
