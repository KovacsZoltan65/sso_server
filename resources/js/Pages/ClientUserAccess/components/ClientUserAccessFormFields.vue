<script setup>
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Textarea from "primevue/textarea";

defineProps({
    form: {
        type: Object,
        required: true,
    },
    clientOptions: {
        type: Array,
        default: () => [],
    },
    userOptions: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="grid gap-5">
        <div class="grid gap-2">
            <label class="text-sm font-medium text-slate-700" for="access-client-id"
                >Client</label
            >
            <Select
                id="access-client-id"
                v-model="form.client_id"
                :options="clientOptions"
                optionLabel="name"
                optionValue="id"
                placeholder="Select client"
                class="w-full"
                :disabled="disabled"
            />
            <small v-if="form.errors.client_id" class="text-red-600">{{
                form.errors.client_id
            }}</small>
        </div>

        <div class="grid gap-2">
            <label class="text-sm font-medium text-slate-700" for="access-user-id"
                >User</label
            >
            <Select
                id="access-user-id"
                v-model="form.user_id"
                :options="userOptions"
                optionLabel="name"
                optionValue="id"
                placeholder="Select user"
                class="w-full"
                :disabled="disabled"
            />
            <small v-if="form.errors.user_id" class="text-red-600">{{
                form.errors.user_id
            }}</small>
        </div>

        <div class="grid gap-2 md:grid-cols-2">
            <!-- Érvényes tól -->
            <div class="grid gap-2">
                <label
                    class="text-sm font-medium text-slate-700"
                    for="access-allowed-from"
                    >Allowed From</label
                >
                <InputText
                    id="access-allowed-from"
                    v-model="form.allowed_from"
                    type="datetime-local"
                    :disabled="disabled"
                />
                <small v-if="form.errors.allowed_from" class="text-red-600">{{
                    form.errors.allowed_from
                }}</small>
            </div>

            <!-- Érvényes ig -->
            <div class="grid gap-2">
                <label
                    class="text-sm font-medium text-slate-700"
                    for="access-allowed-until"
                    >Allowed Until</label
                >
                <InputText
                    id="access-allowed-until"
                    v-model="form.allowed_until"
                    type="datetime-local"
                    :disabled="disabled"
                />
                <small v-if="form.errors.allowed_until" class="text-red-600">{{
                    form.errors.allowed_until
                }}</small>
            </div>
        </div>

        <!-- ACTIVE -->
        <div class="flex items-center gap-3">
            <Checkbox
                inputId="access-active"
                :binary="true"
                v-model="form.is_active"
                :disabled="disabled"
            />
            <label class="text-sm font-medium text-slate-700" for="access-active"
                >Access is active</label
            >
        </div>

        <!-- NOTES -->
        <div class="grid gap-2">
            <label class="text-sm font-medium text-slate-700" for="access-notes"
                >Notes</label
            >
            <Textarea
                id="access-notes"
                v-model="form.notes"
                rows="4"
                :disabled="disabled"
            />
            <small v-if="form.errors.notes" class="text-red-600">{{
                form.errors.notes
            }}</small>
        </div>
    </div>
</template>
