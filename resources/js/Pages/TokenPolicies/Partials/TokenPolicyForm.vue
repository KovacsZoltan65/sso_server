<script setup>
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

defineProps({
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
});
</script>

<template>
    <div class="grid min-h-0 gap-6 xl:grid-cols-3">
        <div class="grid min-w-0 gap-6 xl:col-span-2">
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Basic Information</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Define the human-readable label and the stable technical identifier for this token policy.
                    </p>
                </div>

                <div class="grid gap-2">
                    <label for="token-policy-name" class="text-sm font-medium text-slate-700">Name</label>
                    <InputText id="token-policy-name" v-model="form.name" autocomplete="off" :disabled="loading" fluid />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="grid gap-2">
                    <label for="token-policy-code" class="text-sm font-medium text-slate-700">Code</label>
                    <InputText id="token-policy-code" v-model="form.code" autocomplete="off" :disabled="loading" fluid />
                    <small class="text-slate-500">Use a stable technical identifier such as <code>default.web</code> or <code>public.strict</code>.</small>
                    <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                </div>

                <div class="grid gap-2">
                    <label for="token-policy-description" class="text-sm font-medium text-slate-700">Description</label>
                    <Textarea
                        id="token-policy-description"
                        v-model="form.description"
                        rows="5"
                        autoResize
                        :disabled="loading"
                    />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>
            </section>

            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">TTL Settings</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Store both values in minutes. The refresh TTL must be greater than or equal to the access TTL.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label for="access-token-ttl" class="text-sm font-medium text-slate-700">Access Token TTL (minutes)</label>
                        <InputNumber
                            id="access-token-ttl"
                            v-model="form.access_token_ttl_minutes"
                            :min="1"
                            :useGrouping="false"
                            :disabled="loading"
                            inputClass="w-full"
                            fluid
                        />
                        <small v-if="form.errors.access_token_ttl_minutes" class="text-red-500">
                            {{ form.errors.access_token_ttl_minutes }}
                        </small>
                    </div>

                    <div class="grid gap-2">
                        <label for="refresh-token-ttl" class="text-sm font-medium text-slate-700">Refresh Token TTL (minutes)</label>
                        <InputNumber
                            id="refresh-token-ttl"
                            v-model="form.refresh_token_ttl_minutes"
                            :min="1"
                            :useGrouping="false"
                            :disabled="loading"
                            inputClass="w-full"
                            fluid
                        />
                        <small v-if="form.errors.refresh_token_ttl_minutes" class="text-red-500">
                            {{ form.errors.refresh_token_ttl_minutes }}
                        </small>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid min-w-0 gap-6 xl:col-span-1 xl:self-start">
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Security Rules</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Enable the safeguards that should apply whenever this policy is selected for a client.
                    </p>
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox inputId="rotation-enabled" :binary="true" :modelValue="form.refresh_token_rotation_enabled" :disabled="loading" @update:modelValue="form.refresh_token_rotation_enabled = $event" />
                        <label for="rotation-enabled" class="text-sm text-slate-700">Refresh token rotation enabled</label>
                    </div>
                    <small v-if="form.errors.refresh_token_rotation_enabled" class="text-red-500">{{ form.errors.refresh_token_rotation_enabled }}</small>

                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox inputId="pkce-required" :binary="true" :modelValue="form.pkce_required" :disabled="loading" @update:modelValue="form.pkce_required = $event" />
                        <label for="pkce-required" class="text-sm text-slate-700">PKCE required</label>
                    </div>
                    <small v-if="form.errors.pkce_required" class="text-red-500">{{ form.errors.pkce_required }}</small>

                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox inputId="reuse-forbidden" :binary="true" :modelValue="form.reuse_refresh_token_forbidden" :disabled="loading" @update:modelValue="form.reuse_refresh_token_forbidden = $event" />
                        <label for="reuse-forbidden" class="text-sm text-slate-700">Refresh token reuse forbidden</label>
                    </div>
                    <small v-if="form.errors.reuse_refresh_token_forbidden" class="text-red-500">{{ form.errors.reuse_refresh_token_forbidden }}</small>
                </div>
            </section>

            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Status</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Only one default policy may exist at a time. Default policies are always kept active.
                    </p>
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox inputId="is-default" :binary="true" :modelValue="form.is_default" :disabled="loading" @update:modelValue="form.is_default = $event" />
                        <label for="is-default" class="text-sm text-slate-700">Default policy</label>
                    </div>
                    <small v-if="form.errors.is_default" class="text-red-500">{{ form.errors.is_default }}</small>

                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox inputId="is-active" :binary="true" :modelValue="form.is_active" :disabled="loading" @update:modelValue="form.is_active = $event" />
                        <label for="is-active" class="text-sm text-slate-700">Policy is active</label>
                    </div>
                    <small v-if="form.errors.is_active" class="text-red-500">{{ form.errors.is_active }}</small>
                </div>
            </section>
        </div>
    </div>
</template>
