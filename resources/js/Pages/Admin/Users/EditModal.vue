<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import UserFormFields from '@/Pages/Admin/Users/Partials/UserFormFields.vue';
import { watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['saved', 'update:visible']);

const form = useForm({
    name: '',
    email: '',
    roles: [],
});

const fillForm = () => {
    form.defaults({
        name: props.user?.name ?? '',
        email: props.user?.email ?? '',
        roles: [...(props.user?.roles ?? [])],
    });
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.visible, props.user],
    ([visible, user]) => {
        if (visible && user) {
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
    if (!props.user) {
        return;
    }

    form.put(route('admin.users.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', {
                message: 'User updated successfully.',
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
        header="Edit User"
        :style="{ width: '36rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <form class="grid gap-6" @submit.prevent="submit">
            <UserFormFields
                :form="form"
                :roleOptions="roleOptions"
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
                    :disabled="form.processing || !user"
                />
            </div>
        </form>
    </Dialog>
</template>
