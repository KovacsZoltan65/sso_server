<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { reactive } from 'vue';
import ClientUserAccessFormFields from './ClientUserAccessFormFields.vue';
import { createClientUserAccess } from '@/Services/clientUserAccessService';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
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

const reset = () => {
    form.client_id = null;
    form.user_id = null;
    form.is_active = true;
    form.allowed_from = '';
    form.allowed_until = '';
    form.notes = '';
    form.processing = false;
    form.errors = {};
};

const close = () => {
    if (form.processing) {
        return;
    }

    reset();
    emit('update:visible', false);
};

const submit = async () => {
    form.processing = true;
    form.errors = {};

    try {
        const response = await createClientUserAccess({
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
            type: 'create',
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
        header="Create Client Access"
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
                    label="Create Access"
                    icon="pi pi-check"
                    :loading="form.processing"
                    :disabled="form.processing"
                />
            </div>
        </form>
    </Dialog>
</template>
