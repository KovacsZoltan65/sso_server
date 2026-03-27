<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, watch } from 'vue';

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const formId = 'client-edit-form';

const form = useForm({
    name: props.client.name ?? '',
    client_id: props.client.clientId ?? '',
    redirect_uris: [...(props.client.redirectUris ?? [''])],
    scopes: [...(props.client.scopes ?? [])],
    is_active: props.client.isActive ?? true,
    token_policy_id: props.client.tokenPolicyId ?? null,
});

const rotateForm = useForm({
    name: '',
});

const flashClientSecret = computed(() => page.props.flash?.clientSecret ?? null);
const secrets = computed(() => props.client.secrets ?? []);

watch(
    () => page.props.flash?.success,
    (message) => {
        if (!message) {
            return;
        }

        toast.add({
            severity: 'success',
            summary: 'Sikeres művelet',
            detail: message,
            life: 3000,
        });
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (!message) {
            return;
        }

        toast.add({
            severity: 'error',
            summary: 'Hiba',
            detail: message,
            life: 4000,
        });
    },
    { immediate: true },
);

const submit = () => {
    form.put(route('admin.sso-clients.update', props.client.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route('admin.sso-clients.index'));
};

const rotateSecret = () => {
    rotateForm.post(route('admin.sso-clients.rotate-secret', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            rotateForm.reset();
        },
    });
};

const confirmRevoke = (secret) => {
    confirm.require({
        message: `Biztosan visszavonod ezt a secretet? (...${secret.lastFour ?? '----'})`,
        header: 'Secret visszavonása',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Visszavonás',
        rejectLabel: 'Mégse',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('admin.sso-clients.revoke-secret', [props.client.id, secret.id]), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <Head title="Edit Client" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-form-page">
            <PageHeader
                title="Edit SSO Client"
                description="Update redirect targets, scopes, activation state, and manage secret lifecycle without exposing historical secret values."
            />

            <div
                v-if="flashClientSecret"
                class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-950"
            >
                <div class="font-semibold">Új client secret</div>
                <p class="mt-1 leading-6 text-emerald-900">
                    Mentsd el most. A rendszer ezt az értéket később már nem fogja újra megjeleníteni.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <div class="rounded-2xl bg-white/80 px-3 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-emerald-700">Client ID</div>
                        <div class="mt-1 break-all font-mono text-sm">{{ flashClientSecret.clientId }}</div>
                    </div>
                    <div class="rounded-2xl bg-white/80 px-3 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-emerald-700">Client Secret</div>
                        <div class="mt-1 break-all font-mono text-sm">{{ flashClientSecret.secret }}</div>
                    </div>
                </div>
            </div>

            <div class="admin-form-shell space-y-6 overflow-y-auto pr-1">
                <div class="flex flex-col">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Client Configuration</div>
                                <p class="text-sm text-slate-500">
                                    A client secret korábbi értékei nem olvashatók vissza. Secret módosításhoz használd az alábbi külön kezelőpanelt.
                                </p>
                            </div>
                        </template>

                        <ClientForm
                            :formId="formId"
                            :form="form"
                            mode="edit"
                            :loading="form.processing"
                            :scopeOptions="scopeOptions"
                            :tokenPolicies="tokenPolicies"
                            @submit="submit"
                        />

                        <template #footer>
                            <div class="flex flex-wrap justify-end gap-3">
                                <Button
                                    type="button"
                                    label="Cancel"
                                    severity="secondary"
                                    outlined
                                    :disabled="form.processing"
                                    @click="cancel"
                                />
                                <Button
                                    type="submit"
                                    form="client-edit-form"
                                    label="Save Changes"
                                    icon="pi pi-save"
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                />
                            </div>
                        </template>
                    </AdminFormCard>
                </div>

                <div v-if="canManageSecrets" class="flex flex-col">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Secret lifecycle</div>
                                <p class="text-sm text-slate-500">
                                    Forgatáskor az előző aktív secret azonnal visszavonásra kerül. A plain secret csak egyszer jelenik meg.
                                </p>
                            </div>
                        </template>

                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                            <div class="space-y-3">
                                <div
                                    v-for="secret in secrets"
                                    :key="secret.id"
                                    class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="space-y-2">
                                            <div class="font-medium text-slate-900">{{ secret.name || 'Unnamed secret' }}</div>
                                            <div class="text-sm text-slate-600">
                                                Suffix: <span class="font-mono">...{{ secret.lastFour || '----' }}</span>
                                            </div>
                                            <div class="text-xs text-slate-500">Created: {{ secret.createdAt || '—' }}</div>
                                            <div v-if="secret.revokedAt" class="text-xs text-rose-600">Revoked: {{ secret.revokedAt }}</div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <Tag :value="secret.isRevoked ? 'Revoked' : (secret.isActive ? 'Active' : 'Inactive')" :severity="secret.isRevoked ? 'danger' : (secret.isActive ? 'success' : 'secondary')" />
                                            <Button
                                                v-if="secret.canRevoke"
                                                type="button"
                                                label="Revoke"
                                                severity="danger"
                                                text
                                                icon="pi pi-ban"
                                                @click="confirmRevoke(secret)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form class="space-y-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-4" @submit.prevent="rotateSecret">
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold text-slate-900">Rotate secret</div>
                                    <p class="text-sm text-slate-500">
                                        Opcionálisan adhatsz egy admin címkét az új secretnek.
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <label for="secret-name" class="text-sm font-medium text-slate-700">Secret label</label>
                                    <InputText
                                        id="secret-name"
                                        v-model="rotateForm.name"
                                        placeholder="Pl. Rotated before mobile rollout"
                                        fluid
                                    />
                                    <small v-if="rotateForm.errors.name" class="text-red-500">{{ rotateForm.errors.name }}</small>
                                </div>

                                <Button
                                    type="submit"
                                    label="Rotate Secret"
                                    icon="pi pi-refresh"
                                    :loading="rotateForm.processing"
                                    :disabled="rotateForm.processing"
                                    fluid
                                />
                            </form>
                        </div>
                    </AdminFormCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
