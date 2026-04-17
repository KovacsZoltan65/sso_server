<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { trans } from "laravel-vue-i18n";
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { revokeRememberedConsent } from "@/Services/rememberedConsentService";
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
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { computed, reactive, ref } from "vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";

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
    revocationReasonOptions: {
        type: Array,
        default: () => [],
    },
    canManageRememberedConsents: {
        type: Boolean,
        default: false,
    },
});

const toast = useToast();
const confirm = useConfirm();
const busy = ref(false);
const revokeDialogVisible = ref(false);
const selectedConsent = ref(null);
const selectedRevocationReason = ref(
    props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke"
);

const rows = computed(() => props.rows);
const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    clientId: {
        value: props.filters.client_id ?? null,
        matchMode: FilterMatchMode.EQUALS,
    },
    userId: { value: props.filters.user_id ?? null, matchMode: FilterMatchMode.EQUALS },
    status: { value: props.filters.status ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage ?? 1,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? "grantedAt",
    sortOrder: props.sorting.order ?? -1,
});

const perPageOptions = [5, 10, 15, 25];
const statusOptions = [
    { label: trans("common.all_statuses"), value: null },
    { label: trans("status.active"), value: "active" },
    { label: trans("status.revoked"), value: "revoked" },
    { label: trans("status.expired"), value: "expired" },
];
const clientSelectOptions = computed(() => [
    { label: trans("pages.remembered_consents.all_clients"), value: null },
    ...props.clientOptions.map((client) => ({
        label: `${client.name} (${client.clientId})`,
        value: client.id,
    })),
]);
const userSelectOptions = computed(() => [
    { label: trans("pages.remembered_consents.all_users"), value: null },
    ...props.userOptions.map((user) => ({
        label: `${user.name} (${user.email})`,
        value: user.id,
    })),
]);

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    client_id: tableFilters.value.clientId.value || undefined,
    user_id: tableFilters.value.userId.value || undefined,
    status: tableFilters.value.status.value || undefined,
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const reload = (overrides = {}) => {
    router.get(route("admin.remembered-consents.index"), buildParams(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const refresh = () => {
    reload();

    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: trans("remembered_consents.refresh_detail"),
        life: 2500,
    });
};

const showError = (fallbackMessage = trans("remembered_consents.error_fallback")) => {
    toast.add({
        severity: "error",
        summary: trans("common.error"),
        detail: fallbackMessage,
        life: 4000,
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

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;
    reload({ page: tableState.page, perPage: tableState.perPage });
};

const onSort = (event) => {
    tableState.sortField = event.sortField || "grantedAt";
    tableState.sortOrder = event.sortOrder || -1;
    reload({
        sortField: tableState.sortField,
        sortOrder: tableState.sortOrder,
    });
};

const statusSeverity = (status) => {
    switch (status) {
        case "active":
            return "success";
        case "revoked":
            return "danger";
        case "expired":
            return "warn";
        default:
            return "secondary";
    }
};

const openRevokeDialog = (row) => {
    selectedConsent.value = row;
    selectedRevocationReason.value =
        props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke";
    revokeDialogVisible.value = true;
};

const closeRevokeDialog = () => {
    revokeDialogVisible.value = false;
    selectedConsent.value = null;
    selectedRevocationReason.value =
        props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke";
};

const submitRevokeDialog = () => {
    if (!selectedConsent.value?.id) {
        return;
    }

    const row = selectedConsent.value;
    const payload = {
        revocation_reason: selectedRevocationReason.value,
    };

    confirm.require({
        message: trans("remembered_consents.revoke.confirm_message", { id: row.id }),
        header: trans("remembered_consents.revoke.confirm_title"),
        acceptLabel: trans("remembered_consents.revoke.accept"),
        rejectLabel: trans("common.cancel"),
        accept: async () => {
            busy.value = true;

            try {
                const response = await revokeRememberedConsent(row.id, payload);
                const alreadyRevoked = response?.data?.data?.already_revoked === true;

                toast.add({
                    severity: "success",
                    summary: alreadyRevoked
                        ? trans("remembered_consents.revoke.already_revoked_summary")
                        : trans("remembered_consents.revoke.summary"),
                    detail: alreadyRevoked
                        ? trans("remembered_consents.revoke.already_revoked_detail")
                        : trans("remembered_consents.revoke.detail"),
                    life: 3000,
                });

                closeRevokeDialog();
                reload();
            } catch (error) {
                const message =
                    error?.response?.data?.message ||
                    error?.response?.data?.errors?.revocation_reason?.[0] ||
                    trans("remembered_consents.error_fallback");

                showError(message);
            } finally {
                busy.value = false;
            }
        },
    });
};

const resolveRowActions = (row) => {
    if (!props.canManageRememberedConsents || !row.canRevoke) {
        return [];
    }

    return [
        {
            label: trans("actions.revoke"),
            icon: "pi pi-ban",
            isPrimary: true,
            isDangerous: true,
            command: () => openRevokeDialog(row),
        },
    ];
};

usePageOverlayCleanup(() => {
    closeRevokeDialog();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="trans('remembered_consents.title')" />
        <Toast />
        <ConfirmDialog />
        <Dialog
            :visible="revokeDialogVisible"
            modal
            :header="trans('remembered_consents.revoke.dialog_title')"
            @update:visible="revokeDialogVisible = $event"
        >
            <div class="flex flex-col gap-4">
                <p class="text-sm text-slate-600">
                    {{ trans("remembered_consents.revoke.dialog_description") }}
                    <span class="font-medium">{{
                        selectedConsent?.clientName ?? trans("common.client")
                    }}</span>
                    <span class="font-medium">{{
                        selectedConsent?.userEmail ?? trans("common.user")
                    }}</span
                    >.
                </p>

                <div class="flex flex-col gap-2">
                    <label
                        for="revocation-reason"
                        class="text-sm font-medium text-slate-700"
                        >{{ trans("common.reason") }}</label
                    >
                    <Select
                        id="revocation-reason"
                        v-model="selectedRevocationReason"
                        :options="revocationReasonOptions"
                        option-label="label"
                        option-value="value"
                        :placeholder="
                            trans('pages.remembered_consents.select_revoke_reason')
                        "
                        data-revoke-reason
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <Button
                        :label="trans('common.cancel')"
                        severity="secondary"
                        outlined
                        data-revoke-cancel
                        @click="closeRevokeDialog"
                    />
                    <Button
                        :label="trans('common.continue')"
                        :disabled="busy"
                        data-revoke-submit
                        @click="submitRevokeDialog"
                    />
                </div>
            </div>
        </Dialog>

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                :title="trans('remembered_consents.title')"
                :description="trans('remembered_consents.description')"
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
                                    :placeholder="
                                        trans('remembered_consents.search_placeholder')
                                    "
                                    @keyup.enter="onGlobalSearch"
                                />
                            </IconField>
                        </template>
                    </AdminTableToolbar>

                    <div class="grid gap-3 md:grid-cols-3">
                        <Select
                            v-model="tableFilters.status.value"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('common.status')"
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
                            :loading="loading"
                            :loading-message="
                                trans('remembered_consents.loading_message')
                            "
                            :empty-message="trans('remembered_consents.empty_message')"
                            removable-sort
                            data-key="id"
                            :rows="pagination.perPage"
                            :first="pagination.first"
                            :total-records="pagination.totalRecords"
                            :sort-field="pagination.sortField"
                            :sort-order="pagination.sortOrder"
                            :rows-per-page-options="perPageOptions"
                            @page="onPage"
                            @sort="onSort"
                        >
                            <termplate #header></termplate>
                            <template #empty>
                                <div
                                    class="flex min-h-40 items-center justify-center text-slate-500"
                                >
                                    {{ trans("table.empty") }}
                                </div>
                            </template>

                            <!-- User Name -->
                            <Column
                                field="userName"
                                :header="trans('table.columns.user')"
                                sortable
                            >
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.userName }}</span>
                                        <span class="text-sm text-slate-500">{{
                                            data.userEmail
                                        }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Client Name -->
                            <Column
                                field="clientName"
                                :header="trans('table.columns.client')"
                                sortable
                            >
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.clientName }}</span>
                                        <span class="text-sm text-slate-500">{{
                                            data.clientPublicId
                                        }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Scopes -->
                            <Column
                                field="scopeCodes"
                                :header="trans('table.columns.scopes')"
                            >
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-2" data-consent-scopes>
                                        <Tag
                                            v-for="scope in data.scopeCodes"
                                            :key="scope"
                                            :value="scope"
                                            severity="secondary"
                                        />
                                    </div>
                                </template>
                            </Column>

                            <!-- Trust -->
                            <Column
                                field="trustTierSnapshot"
                                :header="trans('table.columns.trust')"
                            >
                                <template #body="{ data }">
                                    <span>{{ data.trustTierSnapshot }}</span>
                                </template>
                            </Column>

                            <!-- Status -->
                            <Column
                                field="status"
                                :header="trans('table.columns.status')"
                            >
                                <template #body="{ data }">
                                    <Tag
                                        :value="data.status"
                                        :severity="statusSeverity(data.status)"
                                        data-consent-status
                                    />
                                </template>
                            </Column>

                            <!-- Granted -->
                            <Column
                                field="grantedAt"
                                :header="trans('table.columns.granted')"
                                sortable
                            >
                                <template #body="{ data }">
                                    <span>{{ data.grantedAt }}</span>
                                </template>
                            </Column>

                            <!-- Expired -->
                            <Column
                                field="expiresAt"
                                :header="trans('table.columns.expires')"
                                sortable
                            >
                                <template #body="{ data }">
                                    <span>{{ data.expiresAt }}</span>
                                </template>
                            </Column>

                            <!-- Revoked -->
                            <Column
                                field="revokedAt"
                                :header="trans('table.columns.revoked')"
                            >
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{
                                            data.revokedAt ??
                                            trans("common.not_available")
                                        }}</span>
                                        <span
                                            v-if="data.revocationReason"
                                            class="text-sm text-slate-500"
                                            >{{ data.revocationReason }}</span
                                        >
                                    </div>
                                </template>
                            </Column>

                            <!-- Actions -->
                            <Column
                                :header="trans('common.actions')"
                                :style="{ width: '12rem' }"
                            >
                                <template #body="{ data }">
                                    <RowActionMenu
                                        :items="resolveRowActions(data)"
                                        :disabled="
                                            resolveRowActions(data).length === 0 || busy
                                        "
                                    />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
