<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TokenStatusTag from "@/Pages/Tokens/components/TokenStatusTag.vue";
import { revokeToken } from "@/Services/tokenService";
import { Head, router } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
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
});

const toast = useToast();
const confirm = useConfirm();
const busy = ref(false);
const rows = computed(() => props.rows);

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

const perPageOptions = [5, 10, 15, 25];
const tokenTypeOptions = [
    { label: "Access Token", value: "access_token" },
    { label: "Refresh Token", value: "refresh_token" },
];
const stateOptions = [
    { label: "All", value: null },
    { label: "Active", value: "active" },
    { label: "Expired", value: "expired" },
    { label: "Revoked", value: "revoked" },
    { label: "Rotated", value: "rotated" },
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

const showError = (fallbackMessage = "Token action failed.") => {
    toast.add({
        severity: "error",
        summary: "Error",
        detail: fallbackMessage,
        life: 4000,
    });
};

const refresh = () => {
    reload();

    toast.add({
        severity: "success",
        summary: "Refreshed",
        detail: "tokens refreshed successfully.",
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
        return "No family";
    }

    return row.familyId.length > 12 ? `${row.familyId.slice(0, 12)}...` : row.familyId;
};

const resolveRowActions = (row) => {
    if (!props.canManageTokens || !row.canRevoke) {
        return [];
    }

    return [
        {
            label: "Revoke",
            icon: "pi pi-ban",
            command: () => confirmRevoke(row),
        },
    ];
};

const confirmRevoke = (row) => {
    confirm.require({
        message: "Revoke this token?",
        header: "Confirm revoke",
        acceptLabel: "Revoke",
        rejectLabel: "Cancel",
        accept: async () => {
            busy.value = true;

            try {
                await revokeToken(row.id, {
                    token_type: row.tokenType,
                    reason: "admin_revoked",
                });

                toast.add({
                    severity: "success",
                    summary: "Revoked",
                    detail: "Token revoked successfully.",
                    life: 3000,
                });

                reload();
            } catch (error) {
                const message = error?.response?.data?.message
                    || error?.response?.data?.errors?.token_type?.[0]
                    || "Token action failed.";

                showError(message);
            } finally {
                busy.value = false;
            }
        },
    });
};

usePageOverlayCleanup(() => {});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tokens" />
        <Toast />
        <ConfirmDialog />

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                title="Tokens"
                description="Issued access and refresh token metadata with revocation and chain visibility."
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
                                    placeholder="Search by client, user, email, or family"
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
                            placeholder="Token type"
                            @update:model-value="onTokenTypeChange"
                        />
                        <Select
                            v-model="tableFilters.state.value"
                            :options="stateOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="State"
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
                            <Column field="tokenType" header="Type" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.tokenType === "access_token" ? "Access Token" : "Refresh Token" }}</span>
                                </template>
                            </Column>

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

                            <Column field="status" header="Status">
                                <template #body="{ data }">
                                    <div data-token-status>
                                        <TokenStatusTag :status="data.status" />
                                    </div>
                                </template>
                            </Column>

                            <Column field="familyId" header="Family">
                                <template #body="{ data }">
                                    <div class="flex flex-col text-sm" data-token-family>
                                        <span>{{ familyLabel(data) }}</span>
                                        <span v-if="data.parentTokenId" class="text-slate-500">Parent #{{ data.parentTokenId }}</span>
                                        <span v-if="data.replacedByTokenId" class="text-slate-500">Replaced by #{{ data.replacedByTokenId }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="issuedAt" header="Issued" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.issuedAt }}</span>
                                </template>
                            </Column>

                            <Column field="expiresAt" header="Expires" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.expiresAt ?? "N/A" }}</span>
                                </template>
                            </Column>

                            <Column field="revokedAt" header="Revoked">
                                <template #body="{ data }">
                                    <span>{{ data.revokedAt ?? "N/A" }}</span>
                                </template>
                            </Column>

                            <Column header="Actions">
                                <template #body="{ data }">
                                    <RowActionMenu :items="resolveRowActions(data)" :disabled="resolveRowActions(data).length === 0 || busy" />
                                </template>
                            </Column>

                            <template #empty>
                                <div class="flex min-h-40 items-center justify-center text-slate-500">
                                    No tokens match the current filters.
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
