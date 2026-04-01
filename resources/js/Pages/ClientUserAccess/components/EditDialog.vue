<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { reactive, watch } from 'vue';
import ClientUserAccessFormFields from './ClientUserAccessFormFields.vue';
import { updateClientUserAccess } from '@/Services/clientUserAccessService';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    access: {
        type: Object,
        default: null,
    },
    clientOptions: {
        type: Array,
        default: () => [],
    },
    userOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['saved', 'update:visible']);

const toLocalDateTime = (value) => {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 16);
};

const form = reactive({
    client_id: null,
    user_id: null,
    is_active: true,
    allowed_from: '',
    allowed_until: '',
    notes: '',
    processing: false,
    errors: {},
});

const fillForm = () => {
    form.client_id = props.access?.clientId ?? null;
    form.user_id = props.access?.userId ?? null;
    form.is_active = props.access?.isActive ?? true;
    form.allowed_from = toLocalDateTime(props.access?.allowedFrom);
    form.allowed_until = toLocalDateTime(props.access?.allowedUntil);
    form.notes = props.access?.notes ?? '';
    form.errors = {};
};

watch(
    () => [props.visible, props.access],
    ([visible, access]) => {
        if (visible && access) {
            fillForm();
        }
    },
    { immediate: true },
);

const close = () => {
    if (form.processing) {
        return;
    }

    form.errors = {};
    emit('update:visible', false);
};

const submit = async () => {
    if (!props.access) {
        return;
    }

    form.processing = true;
    form.errors = {};

    try {
        const response = await updateClientUserAccess(props.access.id, {
            client_id: Number(form.client_id),
            user_id: Number(form.user_id),
            is_active: form.is_active,
            allowed_from: form.allowed_from || null,
            allowed_until: form.allowed_until || null,
            notes: form.notes || null,
        });

        form.processing = false;
        emit('saved', {
            message: response.message,
            type: 'edit',
        });
        close();
    } catch (error) {
        form.errors = error.response?.data?.errors ?? {};
        form.processing = false;
    }
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        header="Edit Client Access"
        :style="{ width: '42rem', maxWidth: '96vw' }"
        @update:visible="(value) => emit('update:visible', value)"
        @hide="close"
    >
        <form class="grid gap-6" @submit.prevent="submit">
            <ClientUserAccessFormFields
                :form="form"
                :clientOptions="clientOptions"
                :userOptions="userOptions"
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
                    :disabled="form.processing || !access"
                />
            </div>
        </form>
    </Dialog>
</template>
