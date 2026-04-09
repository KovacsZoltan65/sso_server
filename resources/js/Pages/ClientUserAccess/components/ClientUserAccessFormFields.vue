<script setup>
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({
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
    layoutMode: {
        type: String,
        default: 'page',
    },
});

const clientSelectOptions = computed(() => props.clientOptions.map((client) => ({
    label: `${client.name} (${client.clientId})`,
    value: client.id,
})));

const userSelectOptions = computed(() => props.userOptions.map((user) => ({
    label: `${user.name} (${user.email})`,
    value: user.id,
})));

const isModalLayout = computed(() => props.layoutMode === 'modal');
</script>

<template>
    <div
        :class="[
            'grid min-h-0 gap-6',
            isModalLayout ? 'grid-cols-1' : 'grid-cols-1 xl:grid-cols-3',
        ]"
    >
        <div :class="['grid min-w-0 gap-5', isModalLayout ? 'xl:col-span-1' : 'xl:col-span-2']">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700" for="access-client-id">Client</label>
                    <Select
                        id="access-client-id"
                        v-model="form.client_id"
                        :options="clientSelectOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select client"
                        class="w-full"
                        :disabled="disabled"
                    />
                    <small v-if="form.errors.client_id" class="text-red-600">{{ form.errors.client_id }}</small>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700" for="access-user-id">User</label>
                    <Select
                        id="access-user-id"
                        v-model="form.user_id"
                        :options="userSelectOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select user"
                        class="w-full"
                        :disabled="disabled"
                    />
                    <small v-if="form.errors.user_id" class="text-red-600">{{ form.errors.user_id }}</small>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700" for="access-allowed-from">Allowed From</label>
                    <InputText
                        id="access-allowed-from"
                        v-model="form.allowed_from"
                        type="datetime-local"
                        :disabled="disabled"
                    />
                    <small v-if="form.errors.allowed_from" class="text-red-600">{{ form.errors.allowed_from }}</small>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700" for="access-allowed-until">Allowed Until</label>
                    <InputText
                        id="access-allowed-until"
                        v-model="form.allowed_until"
                        type="datetime-local"
                        :disabled="disabled"
                    />
                    <small v-if="form.errors.allowed_until" class="text-red-600">{{ form.errors.allowed_until }}</small>
                </div>
            </div>

            <div class="grid gap-2">
                <label class="text-sm font-medium text-slate-700" for="access-notes">Notes</label>
                <Textarea
                    id="access-notes"
                    v-model="form.notes"
                    rows="6"
                    :disabled="disabled"
                />
                <small v-if="form.errors.notes" class="text-red-600">{{ form.errors.notes }}</small>
            </div>
        </div>

        <div
            :class="[
                'grid min-w-0 gap-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-4',
                isModalLayout ? 'self-auto' : 'self-start',
            ]"
        >
            <div class="grid gap-2">
                <label class="text-sm font-medium text-slate-700">Status</label>
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                    <Checkbox
                        inputId="access-active"
                        :binary="true"
                        v-model="form.is_active"
                        :disabled="disabled"
                    />
                    <label class="text-sm text-slate-700" for="access-active">Access is active</label>
                </div>
                <small v-if="form.errors.is_active" class="text-red-600">{{ form.errors.is_active }}</small>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="font-medium">Restriction behavior</div>
                <p class="mt-1 leading-6 text-amber-800">
                    When a client has no active access records, it remains open. Adding at least one active record restricts authorization to explicitly assigned users.
                </p>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <div class="font-medium">Time window</div>
                <p class="mt-1 leading-6 text-sky-800">
                    Leave the allowed-from and allowed-until values empty to grant access without a time-bound window.
                </p>
            </div>
        </div>
    </div>
</template>
