<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
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
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
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
const selectedRevocationReason = ref(props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke");

const rows = computed(() => props.rows);
const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    clientId: { value: props.filters.client_id ?? null, matchMode: FilterMatchMode.EQUALS },
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
    { label: "All statuses", value: null },
    { label: "Active", value: "active" },
    { label: "Revoked", value: "revoked" },
    { label: "Expired", value: "expired" },
];
const clientSelectOptions = computed(() => [
    { label: "All clients", value: null },
    ...props.clientOptions.map((client) => ({
        label: `${client.name} (${client.clientId})`,
        value: client.id,
    })),
]);
const userSelectOptions = computed(() => [
    { label: "All users", value: null },
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
        summary: "Refreshed",
        detail: "Remembered consents refreshed successfully.",
        life: 2500,
    });
};

const showError = (fallbackMessage = "Remembered consent action failed.") => {
    toast.add({
        severity: "error",
        summary: "Error",
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
    selectedRevocationReason.value = props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke";
    revokeDialogVisible.value = true;
};

const closeRevokeDialog = () => {
    revokeDialogVisible.value = false;
    selectedConsent.value = null;
    selectedRevocationReason.value = props.revocationReasonOptions[0]?.value ?? "admin_manual_revoke";
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
        message: `Revoke remembered consent #${row.id}?`,
        header: "Confirm revoke",
        acceptLabel: "Revoke",
        rejectLabel: "Cancel",
        accept: async () => {
            busy.value = true;

            try {
                const response = await revokeRememberedConsent(row.id, payload);
                const alreadyRevoked = response?.data?.data?.already_revoked === true;

                toast.add({
                    severity: "success",
                    summary: alreadyRevoked ? "Already revoked" : "Revoked",
                    detail: alreadyRevoked
                        ? "Remembered consent was already revoked."
                        : "Remembered consent revoked successfully.",
                    life: 3000,
                });

                closeRevokeDialog();
                reload();
            } catch (error) {
                const message = error?.response?.data?.message
                    || error?.response?.data?.errors?.revocation_reason?.[0]
                    || "Remembered consent action failed.";

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

    return [{
        label: "Revoke",
        icon: "pi pi-ban",
        command: () => openRevokeDialog(row),
    }];
};

usePageOverlayCleanup(() => {
    closeRevokeDialog();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Remembered Consents" />
        <Toast />
        <ConfirmDialog />
        <Dialog :visible="revokeDialogVisible" modal header="Revoke Remembered Consent" @update:visible="revokeDialogVisible = $event">
            <div class="flex flex-col gap-4">
                <p class="text-sm text-slate-600">
                    Revoke remembered consent for
                    <span class="font-medium">{{ selectedConsent?.clientName ?? "selected client" }}</span>
                    and
                    <span class="font-medium">{{ selectedConsent?.userEmail ?? "selected user" }}</span>.
                </p>

                <div class="flex flex-col gap-2">
                    <label for="revocation-reason" class="text-sm font-medium text-slate-700">Reason</label>
                    <Select
                        id="revocation-reason"
                        v-model="selectedRevocationReason"
                        :options="revocationReasonOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select revoke reason"
                        data-revoke-reason
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <Button label="Cancel" severity="secondary" outlined data-revoke-cancel @click="closeRevokeDialog" />
                    <Button label="Continue" :disabled="busy" data-revoke-submit @click="submitRevokeDialog" />
                </div>
            </div>
        </Dialog>

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                title="Remembered Consents"
                description="Review stored remembered consent grants and revoke them when policy or security requires it."
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
                                    placeholder="Search by client, user, email, or revoke reason"
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
                            placeholder="Status"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.clientId.value"
                            :options="clientSelectOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Client"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.userId.value"
                            :options="userSelectOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="User"
                            @change="onTableFilter"
                        />
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden">
                        <DataTable
                            :value="rows"
                            :filters="tableFilters"
                            data-key="id"
                            lazy
                            paginator
                            scrollable
                            scroll-height="flex"
                            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                            current-page-report-template="{first} to {last} of {totalRecords}"
                            :rows="pagination.perPage"
                            :first="pagination.first"
                            :total-records="pagination.total"
                            :rows-per-page-options="perPageOptions"
                            :always-show-paginator="true"
                            @page="onPage"
                            @sort="onSort"
                        >
                            <Column field="userName" header="User" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.userName }}</span>
                                        <span class="text-sm text-slate-500">{{ data.userEmail }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="clientName" header="Client" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.clientName }}</span>
                                        <span class="text-sm text-slate-500">{{ data.clientPublicId }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="scopeCodes" header="Scopes">
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

                            <Column field="trustTierSnapshot" header="Trust">
                                <template #body="{ data }">
                                    <span>{{ data.trustTierSnapshot }}</span>
                                </template>
                            </Column>

                            <Column field="status" header="Status">
                                <template #body="{ data }">
                                    <Tag :value="data.status" :severity="statusSeverity(data.status)" data-consent-status />
                                </template>
                            </Column>

                            <Column field="grantedAt" header="Granted" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.grantedAt }}</span>
                                </template>
                            </Column>

                            <Column field="expiresAt" header="Expires" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.expiresAt }}</span>
                                </template>
                            </Column>

                            <Column field="revokedAt" header="Revoked">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span>{{ data.revokedAt ?? "N/A" }}</span>
                                        <span v-if="data.revocationReason" class="text-sm text-slate-500">{{ data.revocationReason }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Actions">
                                <template #body="{ data }">
                                    <RowActionMenu :items="resolveRowActions(data)" :disabled="resolveRowActions(data).length === 0 || busy" />
                                </template>
                            </Column>

                            <template #empty>
                                <div class="flex min-h-40 items-center justify-center text-slate-500">
                                    No remembered consents match the current filters.
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
