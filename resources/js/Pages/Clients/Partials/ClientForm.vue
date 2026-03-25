<script setup>
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        default: 'create',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    formId: {
        type: String,
        default: 'client-form',
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
    showActions: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
});

const emit = defineEmits(['submit', 'cancel']);

const redirectUris = computed(() => {
    if (!Array.isArray(props.form.redirect_uris) || props.form.redirect_uris.length === 0) {
        props.form.redirect_uris = [''];
    }

    return props.form.redirect_uris;
});

const addRedirectUri = () => {
    props.form.redirect_uris = [...redirectUris.value, ''];
};

const removeRedirectUri = (index) => {
    if (redirectUris.value.length === 1) {
        props.form.redirect_uris = [''];
        return;
    }

    props.form.redirect_uris = redirectUris.value.filter((_, currentIndex) => currentIndex !== index);
};
</script>

<template>
    <form :id="formId" class="grid gap-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.6fr)]">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <label for="client-name" class="text-sm font-medium text-slate-700">Name</label>
                    <InputText
                        id="client-name"
                        v-model="form.name"
                        autocomplete="off"
                        :disabled="loading"
                        fluid
                    />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="grid gap-2">
                    <label for="client-id" class="text-sm font-medium text-slate-700">Client ID</label>
                    <InputText
                        id="client-id"
                        :modelValue="mode === 'edit' ? (form.client_id || form.clientId || '') : 'Generated automatically after create'"
                        :disabled="true"
                        fluid
                    />
                    <small class="text-slate-500">
                        {{ mode === 'create'
                            ? 'The client ID is generated automatically when the client is created.'
                            : 'The client ID is immutable after creation.' }}
                    </small>
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium text-slate-700">Redirect URIs</label>
                        <Button
                            type="button"
                            label="Add URI"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="loading"
                            @click="addRedirectUri"
                        />
                    </div>

                    <div
                        v-for="(redirectUri, index) in redirectUris"
                        :key="`redirect-uri-${index}`"
                        class="grid gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-3"
                    >
                        <div class="flex items-start gap-3">
                            <InputText
                                :id="`redirect-uri-${index}`"
                                v-model="form.redirect_uris[index]"
                                :disabled="loading"
                                placeholder="https://client.example.com/callback"
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                :disabled="loading"
                                @click="removeRedirectUri(index)"
                            />
                        </div>
                        <small v-if="form.errors[`redirect_uris.${index}`]" class="text-red-500">
                            {{ form.errors[`redirect_uris.${index}`] }}
                        </small>
                    </div>

                    <small v-if="form.errors.redirect_uris" class="text-red-500">
                        {{ form.errors.redirect_uris }}
                    </small>
                </div>
            </div>

            <div class="grid gap-4 self-start rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                <div class="grid gap-2">
                    <label for="client-scopes" class="text-sm font-medium text-slate-700">Scopes</label>
                    <MultiSelect
                        id="client-scopes"
                        v-model="form.scopes"
                        :options="scopeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :disabled="loading"
                        display="chip"
                        filter
                        placeholder="Select scopes"
                        class="w-full"
                    />
                    <small v-if="form.errors.scopes" class="text-red-500">{{ form.errors.scopes }}</small>
                </div>

                <div v-if="tokenPolicies.length" class="grid gap-2">
                    <label for="client-token-policy" class="text-sm font-medium text-slate-700">Token policy</label>
                    <Select
                        id="client-token-policy"
                        v-model="form.token_policy_id"
                        :options="tokenPolicies"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        placeholder="Select a token policy"
                        :disabled="loading"
                        class="w-full"
                    />
                    <small v-if="form.errors.token_policy_id" class="text-red-500">
                        {{ form.errors.token_policy_id }}
                    </small>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox
                            inputId="client-is-active"
                            :binary="true"
                            :modelValue="form.is_active"
                            :disabled="loading"
                            @update:modelValue="form.is_active = $event"
                        />
                        <label for="client-is-active" class="text-sm text-slate-700">
                            Client is active
                        </label>
                    </div>
                    <small v-if="form.errors.is_active" class="text-red-500">{{ form.errors.is_active }}</small>
                </div>

                <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <div class="font-medium">Secret handling</div>
                    <p class="mt-1 leading-6 text-sky-800">
                        The client secret is generated automatically during create and shown exactly once after success.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="showActions" class="flex flex-wrap justify-end gap-3">
            <Button
                type="button"
                :label="cancelLabel"
                severity="secondary"
                text
                :disabled="loading"
                @click="emit('cancel')"
            />
            <Button
                type="submit"
                :label="submitLabel"
                icon="pi pi-check"
                :loading="loading"
                :disabled="loading"
            />
        </div>
    </form>
</template>
