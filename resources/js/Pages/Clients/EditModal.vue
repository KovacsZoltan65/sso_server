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
    client: {
        type: Object,
        default: null,
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

const emit = defineEmits(['updated', 'close', 'update:visible']);

const form = useForm({
    name: '',
    client_id: '',
    redirect_uris: [''],
    scopes: [],
    is_active: true,
    token_policy_id: null,
});

const fillForm = () => {
    form.defaults({
        name: props.client?.name ?? '',
        client_id: props.client?.clientId ?? '',
        redirect_uris: [...(props.client?.redirectUris ?? [''])],
        scopes: [...(props.client?.scopes ?? [])],
        is_active: props.client?.isActive ?? true,
        token_policy_id: props.client?.tokenPolicyId ?? null,
    });
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.visible, props.client],
    ([visible, client]) => {
        if (visible && client) {
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
    emit('close');
    emit('update:visible', false);
};

const submit = () => {
    if (!props.client) {
        return;
    }

    form.put(route('admin.sso-clients.update', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated', {
                message: 'SSO client updated successfully.',
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
        header="Edit SSO Client"
        :style="{ width: '56rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <ClientForm
            formId="client-edit-modal-form"
            :form="form"
            mode="edit"
            :loading="form.processing"
            :scopeOptions="scopeOptions"
            :tokenPolicies="tokenPolicies"
            showActions
            submitLabel="Save Changes"
            @submit="submit"
            @cancel="close"
        />
    </Dialog>
</template>
