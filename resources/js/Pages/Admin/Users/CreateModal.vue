<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import UserFormFields from '@/Pages/Admin/Users/Partials/UserFormFields.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
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
    password: '',
    password_confirmation: '',
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
    form.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', {
                message: 'User created successfully.',
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
        header="Create User"
        :style="{ width: '36rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <form class="grid gap-6" @submit.prevent="submit">
            <UserFormFields
                :form="form"
                :roleOptions="roleOptions"
                :showPasswordFields="true"
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
                    label="Create User"
                    icon="pi pi-check"
                    :loading="form.processing"
                    :disabled="form.processing"
                />
            </div>
        </form>
    </Dialog>
</template>
