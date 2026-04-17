<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { trans } from 'laravel-vue-i18n';
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TokenStatusTag from "@/Pages/Tokens/components/TokenStatusTag.vue";
import { revokeToken, revokeTokenFamily } from "@/Services/tokenService";
import { Head, router } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import Dialog from "primevue/dialog";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Toast from "primevue/toast";
import { computed, reactive, ref } from "vue";

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    sorting: {
        type: Object,
        required: true,
    },
    pagination: {
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
    canManageTokens: {
        type: Boolean,
        default: false,
    },
    canManageTokenFamilies: {
        type: Boolean,
        default: false,
    },
});

const toast = useToast();
const confirm = useConfirm();
const busy = ref(false);
const rows = computed(() => props.rows);
const familyDialogVisible = ref(false);
const selectedFamilyRow = ref(null);
const familyRevokeReason = ref("");

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    tokenType: { value: props.filters.token_type ?? "refresh_token", matchMode: FilterMatchMode.EQUALS },
    state: { value: props.filters.state ?? null, matchMode: FilterMatchMode.EQUALS },
    clientId: { value: props.filters.client_id ?? null, matchMode: FilterMatchMode.EQUALS },
    userId: { value: props.filters.user_id ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage ?? 1,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? "createdAt",
    sortOrder: props.sorting.order ?? -1,
});

const tokenTypeOptions = [
    { label: trans("pages.tokens.access_token"), value: "access_token" },
    { label: trans("pages.tokens.refresh_token"), value: "refresh_token" },
];
const stateOptions = [
    { label: trans("common.all"), value: null },
    { label: trans("status.active"), value: "active" },
    { label: trans("status.expired"), value: "expired" },
    { label: trans("status.revoked"), value: "revoked" },
    { label: trans("status.rotated"), value: "rotated" },
    { label: trans("status.suspicious"), value: "suspicious" },
    { label: trans("status.family_revoked"), value: "family_revoked" },
];
const clientSelectOptions = computed(() => [
    { label: trans("pages.tokens.all_clients"), value: null },
    ...props.clientOptions.map((client) => ({
        label: `${client.name} (${client.clientId})`,
        value: client.id,
    })),
]);
const userSelectOptions = computed(() => [
    { label: trans("pages.tokens.all_users"), value: null },
    ...props.userOptions.map((user) => ({
        label: `${user.name} (${user.email})`,
        value: user.id,
    })),
]);

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    token_type: tableFilters.value.tokenType.value || "refresh_token",
    state: tableFilters.value.state.value || undefined,
    client_id: tableFilters.value.clientId.value || undefined,
    user_id: tableFilters.value.userId.value || undefined,
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const reload = (overrides = {}) => {
    router.get(route("admin.tokens.index"), buildParams(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const showError = (fallbackMessage = trans('tokens.error_fallback')) => {
    toast.add({
        severity: "error",
        summary: trans('common.error'),
        detail: fallbackMessage,
        life: 4000,
    });
};

const refresh = () => {
    reload();

    toast.add({
        severity: "success",
        summary: trans('common.success'),
        detail: trans('tokens.refresh_detail'),
        life: 2500,
    });
};

const onGlobalSearch = () => {
    tableState.page = 1;
    reload({ page: 1 });
};

const onTableFilter = () => {
    tableState.page = 1;
    reload({ page: 1 });
};

const onTokenTypeChange = (value) => {
    tableFilters.value.tokenType.value = value;
    tableState.page = 1;
    reload({ page: 1, token_type: value });
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;
    reload({ page: tableState.page, perPage: tableState.perPage });
};

const onSort = (event) => {
    tableState.sortField = event.sortField || "createdAt";
    tableState.sortOrder = event.sortOrder || -1;
    reload({
        sortField: tableState.sortField,
        sortOrder: tableState.sortOrder,
    });
};

const familyLabel = (row) => {
    if (!row.familyId) {
        return trans('tokens.family.none');
    }

    return row.familyId.length > 12 ? `${row.familyId.slice(0, 12)}...` : row.familyId;
};

const resolveRowActions = (row) => {
    if (!props.canManageTokens && !props.canManageTokenFamilies) {
        return [];
    }

    return [
        ...(props.canManageTokens && row.canRevoke ? [{
            label: trans("actions.revoke"),
            icon: "pi pi-ban",
            isPrimary: true,
            isDangerous: true,
            command: () => confirmRevoke(row),
        }] : []),
        ...(props.canManageTokenFamilies && row.canRevokeFamily ? [{
            label: trans("actions.revoke_family"),
            icon: "pi pi-shield",
            isDangerous: true,
            command: () => openFamilyDialog(row),
        }] : []),
    ];
};

const confirmRevoke = (row) => {
    confirm.require({
        message: trans('tokens.revoke.confirm_message'),
        header: trans('tokens.revoke.confirm_title'),
        acceptLabel: trans('tokens.revoke.accept'),
        rejectLabel: trans('common.cancel'),
        accept: async () => {
            busy.value = true;

            try {
                await revokeToken(row.id, {
                    token_type: row.tokenType,
                    reason: "admin_revoked",
                });

                toast.add({
                    severity: "success",
                    summary: trans('tokens.revoke.summary'),
                    detail: trans('tokens.revoke.detail'),
                    life: 3000,
                });

                reload();
            } catch (error) {
                const message = error?.response?.data?.message
                    || error?.response?.data?.errors?.token_type?.[0]
                    || trans('tokens.error_fallback');

                showError(message);
            } finally {
                busy.value = false;
            }
        },
    });
};

const openFamilyDialog = (row) => {
    selectedFamilyRow.value = row;
    familyRevokeReason.value = "";
    familyDialogVisible.value = true;
};

const closeFamilyDialog = () => {
    familyDialogVisible.value = false;
    selectedFamilyRow.value = null;
    familyRevokeReason.value = "";
};

const submitFamilyDialog = () => {
    if (!selectedFamilyRow.value?.familyId) {
        return;
    }

    const row = selectedFamilyRow.value;
    const reason = familyRevokeReason.value?.trim() || "admin_family_revoked";

    confirm.require({
        message: trans('tokens.family.confirm_message', { family: familyLabel(row) }),
        header: trans('tokens.family.confirm_title'),
        acceptLabel: trans('tokens.family.accept'),
        rejectLabel: trans('common.cancel'),
        accept: async () => {
            busy.value = true;

            try {
                await revokeTokenFamily(row.familyId, {
                    reason,
                });

                toast.add({
                    severity: "success",
                    summary: trans('tokens.family.summary'),
                    detail: trans('tokens.family.detail'),
                    life: 3000,
                });

                closeFamilyDialog();
                reload();
            } catch (error) {
                const message = error?.response?.data?.message
                    || error?.response?.data?.errors?.family_id?.[0]
                    || error?.response?.data?.errors?.reason?.[0]
                    || trans('tokens.error_fallback');

                showError(message);
            } finally {
                busy.value = false;
            }
        },
    });
};

usePageOverlayCleanup(() => {
    closeFamilyDialog();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="trans('tokens.title')" />
        <Toast />
        <ConfirmDialog />
        <Dialog :visible="familyDialogVisible" modal :header="trans('tokens.family.dialog_title')" @update:visible="familyDialogVisible = $event">
            <div class="flex flex-col gap-4">
                <p class="text-sm text-slate-600">
                    {{ trans('tokens.family.dialog_description') }}
                    <span class="font-medium">{{ selectedFamilyRow?.clientName ?? trans('common.client') }}</span>.
                </p>
                <div class="flex flex-col gap-2">
                    <label for="family-reason" class="text-sm font-medium text-slate-700">{{ trans('common.reason') }}</label>
                    <InputText
                        id="family-reason"
                        v-model="familyRevokeReason"
                        :placeholder="trans('tokens.family.reason_placeholder')"
                        data-family-reason
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <Button :label="trans('common.cancel')" severity="secondary" outlined data-family-cancel @click="closeFamilyDialog" />
                    <Button :label="trans('common.continue')" :disabled="busy" data-family-submit @click="submitFamilyDialog" />
                </div>
            </div>
        </Dialog>

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                :title="trans('tokens.title')"
                :description="trans('tokens.description')"
            />

            <AdminTableCard>
                <div class="flex min-h-0 flex-1 flex-col gap-4 p-6">
                    <AdminTableToolbar
                        :busy="busy"
                        :can-create="false"
                        :can-bulk-delete="false"
                        :selected-count="0"
                        :selectable-count="0"
                        @refresh="refresh"
                    >
                        <template #search>
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="tableFilters.global.value"
                                    class="w-full"
                                    :placeholder="trans('tokens.search_placeholder')"
                                    @keyup.enter="onGlobalSearch"
                                />
                            </IconField>
                        </template>
                    </AdminTableToolbar>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <Select
                            :model-value="tableFilters.tokenType.value"
                            :options="tokenTypeOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('table.columns.type')"
                            @update:model-value="onTokenTypeChange"
                        />
                        <Select
                            v-model="tableFilters.state.value"
                            :options="stateOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('table.columns.state')"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.clientId.value"
                            :options="clientSelectOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('common.client')"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.userId.value"
                            :options="userSelectOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('common.user')"
                            @change="onTableFilter"
                        />
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden">
                        <BaseDataTable
                            :value="rows"
                            :filters="tableFilters"
                            data-key="id"
                            lazy
                            paginator
                            scrollable
                            scroll-height="flex"
                            :striped-rows="false"
                            :rows="pagination.perPage"
                            :first="pagination.first"
                            :total-records="pagination.total"
                            :always-show-paginator="true"
                            @page="onPage"
                            @sort="onSort"
                        >
                            <Column field="tokenType" :header="trans('table.columns.type')" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.tokenType === "access_token" ? trans('pages.tokens.access_token') : trans('pages.tokens.refresh_token') }}</span>
                                </template>
                            </Column>

                            <Column field="userName" :header="trans('table.columns.user')" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.userName }}</span>
                                        <span class="text-sm text-slate-500">{{ data.userEmail }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="clientName" :header="trans('table.columns.client')" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.clientName }}</span>
                                        <span class="text-sm text-slate-500">{{ data.clientPublicId }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="status" :header="trans('table.columns.status')">
                                <template #body="{ data }">
                                    <div data-token-status class="flex items-center gap-2">
                                        <TokenStatusTag :status="data.status" />
                                        <span v-if="data.suspiciousIncident" class="text-xs font-medium text-amber-700" data-token-suspicious>
                                            {{ trans('status.incident') }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="familyId" :header="trans('table.columns.family')">
                                <template #body="{ data }">
                                    <div class="flex flex-col text-sm" data-token-family>
                                        <span>{{ familyLabel(data) }}</span>
                                        <span v-if="data.parentTokenId" class="text-slate-500">{{ trans('pages.tokens.parent_token', { id: data.parentTokenId }) }}</span>
                                        <span v-if="data.replacedByTokenId" class="text-slate-500">{{ trans('pages.tokens.replaced_by_token', { id: data.replacedByTokenId }) }}</span>
                                        <span v-if="data.familyRevoked" class="text-slate-500">{{ trans('status.family_revoked') }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="issuedAt" :header="trans('table.columns.issued')" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.issuedAt }}</span>
                                </template>
                            </Column>

                            <Column field="expiresAt" :header="trans('table.columns.expires')" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.expiresAt ?? trans('common.not_available') }}</span>
                                </template>
                            </Column>

                            <Column field="revokedAt" :header="trans('table.columns.revoked')">
                                <template #body="{ data }">
                                    <span>{{ data.revokedAt ?? trans('common.not_available') }}</span>
                                </template>
                            </Column>

                            <Column :header="trans('table.columns.actions')" :style="{ width: '12rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="resolveRowActions(data)" :disabled="resolveRowActions(data).length === 0 || busy" />
                                </template>
                            </Column>

                            <template #empty>
                                <div class="flex min-h-40 items-center justify-center text-slate-500">
                                    {{ trans('table.empty') }}
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
