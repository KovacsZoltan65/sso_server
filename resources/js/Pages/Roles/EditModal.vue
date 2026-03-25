<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    role: {
        type: Object,
        default: null,
    },
    permissionOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['saved', 'update:visible']);

const form = useForm({
    name: '',
    permissions: [],
});

const fillForm = () => {
    form.defaults({
        name: props.role?.name ?? '',
        permissions: [...(props.role?.permissions ?? [])],
    });
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.visible, props.role],
    ([visible, role]) => {
        if (visible && role) {
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
    if (!props.role) {
        return;
    }

    form.put(route('admin.roles.update', props.role.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', {
                message: 'Role updated successfully.',
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
        header="Edit Role"
        :style="{ width: '36rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <form class="grid gap-6" @submit.prevent="submit">
            <RoleFormFields
                :form="form"
                :permissionOptions="permissionOptions"
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
                    :disabled="form.processing || !role"
                />
            </div>
        </form>
    </Dialog>
</template>
