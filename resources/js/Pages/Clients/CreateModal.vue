<script setup>
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['created', 'close', 'update:visible']);

const form = useForm({
    name: '',
    redirect_uris: [''],
    scopes: [],
    is_active: true,
    token_policy_id: null,
});

const resetForm = () => {
    form.defaults({
        name: '',
        redirect_uris: [''],
        scopes: [],
        is_active: true,
        token_policy_id: null,
    });
    form.reset();
    form.clearErrors();
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetForm();
        }
    },
    { immediate: true },
);

const close = () => {
    if (form.processing) {
        return;
    }

    form.clearErrors();
    emit('close');
    emit('update:visible', false);
};

const submit = () => {
    form.post(route('admin.sso-clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('created', {
                message: 'SSO client created successfully.',
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
        header="Create SSO Client"
        :style="{ width: '56rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <ClientForm
            formId="client-create-modal-form"
            :form="form"
            mode="create"
            :loading="form.processing"
            :scopeOptions="scopeOptions"
            :tokenPolicies="tokenPolicies"
            showActions
            submitLabel="Create Client"
            @submit="submit"
            @cancel="close"
        />
    </Dialog>
</template>
