<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { fetchAuditLog } from "@/Services/auditLogService";
import { Head, router } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import { useToast } from "primevue/usetoast";
import Button from "primevue/button";
import Column from "primevue/column";
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
    filterOptions: {
        type: Object,
        default: () => ({
            categories: [],
            severities: [],
            actorTypes: [],
            subjectTypes: [],
            clients: [],
        }),
    },
});

const toast = useToast();
const busy = ref(false);
const detailVisible = ref(false);
const detailBusy = ref(false);
const detail = ref(null);
const rows = computed(() => props.rows);
const perPageOptions = [10, 15, 25, 50];
const categoryOptions = computed(() => [
    { label: "All categories", value: null },
    ...(props.filterOptions.categories ?? []),
]);
const severityOptions = computed(() => [
    { label: "All severities", value: null },
    ...(props.filterOptions.severities ?? []),
]);
const actorTypeOptions = computed(() => [
    { label: "All actor types", value: null },
    ...(props.filterOptions.actorTypes ?? []),
]);
const subjectTypeOptions = computed(() => [
    { label: "All subject types", value: null },
    ...(props.filterOptions.subjectTypes ?? []),
]);
const clientOptions = computed(() => [
    { label: "All clients", value: null },
    ...(props.filterOptions.clients ?? []),
]);

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    eventType: { value: props.filters.event_type ?? null, matchMode: FilterMatchMode.CONTAINS },
    category: { value: props.filters.category ?? null, matchMode: FilterMatchMode.EQUALS },
    severity: { value: props.filters.severity ?? null, matchMode: FilterMatchMode.EQUALS },
    actorType: { value: props.filters.actor_type ?? null, matchMode: FilterMatchMode.EQUALS },
    subjectType: { value: props.filters.subject_type ?? null, matchMode: FilterMatchMode.EQUALS },
    clientId: { value: props.filters.client_id ?? null, matchMode: FilterMatchMode.EQUALS },
    dateFrom: { value: props.filters.date_from ?? null, matchMode: FilterMatchMode.EQUALS },
    dateTo: { value: props.filters.date_to ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage ?? 1,
    perPage: props.pagination.perPage ?? 15,
    sortField: props.sorting.field ?? "occurred_at",
    sortOrder: props.sorting.order ?? -1,
});

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    event_type: tableFilters.value.eventType.value || undefined,
    category: tableFilters.value.category.value || undefined,
    severity: tableFilters.value.severity.value || undefined,
    actor_type: tableFilters.value.actorType.value || undefined,
    subject_type: tableFilters.value.subjectType.value || undefined,
    client_id: tableFilters.value.clientId.value || undefined,
    date_from: tableFilters.value.dateFrom.value || undefined,
    date_to: tableFilters.value.dateTo.value || undefined,
    page: tableState.page,
    per_page: tableState.perPage,
    sort_field: tableState.sortField || undefined,
    sort_order: tableState.sortOrder || undefined,
    ...overrides,
});

const reload = (overrides = {}) => {
    router.get(route("admin.audit-logs.index"), buildParams(overrides), {
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
        detail: "Audit logs refreshed successfully.",
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

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;
    reload({ page: tableState.page, per_page: tableState.perPage });
};

const onSort = (event) => {
    tableState.sortField = event.sortField || "occurred_at";
    tableState.sortOrder = event.sortOrder || -1;
    reload({
        sort_field: tableState.sortField,
        sort_order: tableState.sortOrder,
    });
};

const severityLabel = (value) => {
    if (!value) {
        return "Info";
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
};

const severityVariant = (value) => {
    switch (value) {
    case "critical":
        return "danger";
    case "error":
        return "danger";
    case "warning":
        return "warn";
    default:
        return "info";
    }
};

const summaryText = (value) => value || "No summary available.";
const actorText = (actor) => actor?.display || "System";
const subjectText = (subject) => subject?.display || "N/A";
const clientText = (client) => client ? `${client.display} (${client.clientId})` : "N/A";

const resolveRowActions = (row) => [{
    label: "Details",
    icon: "pi pi-eye",
    command: () => openDetail(row),
}];

const closeDetail = () => {
    detailVisible.value = false;
    detail.value = null;
};

const openDetail = async (row) => {
    detailVisible.value = true;
    detailBusy.value = true;
    detail.value = null;

    try {
        const response = await fetchAuditLog(row.id);
        detail.value = response?.data?.data ?? null;
    } catch (error) {
        closeDetail();
        toast.add({
            severity: "error",
            summary: "Error",
            detail: error?.response?.data?.message || "Unable to load audit log details.",
            life: 4000,
        });
    } finally {
        detailBusy.value = false;
    }
};

const formattedMeta = computed(() => {
    if (!detail.value?.meta) {
        return "{}";
    }

    return JSON.stringify(detail.value.meta, null, 2);
});

usePageOverlayCleanup(() => {
    closeDetail();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Audit Logs" />
        <Toast />

        <Dialog
            :visible="detailVisible"
            modal
            header="Audit Log Details"
            :style="{ width: 'min(56rem, 96vw)' }"
            @update:visible="detailVisible = $event"
        >
            <div v-if="detailBusy" class="flex min-h-40 items-center justify-center text-slate-500">
                Loading audit log details...
            </div>

            <div v-else-if="detail" class="flex flex-col gap-5" data-audit-detail>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Event</div>
                        <div class="mt-1 text-sm text-slate-800">{{ detail.eventType }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Severity</div>
                        <div class="mt-1">
                            <Tag :value="severityLabel(detail.severity)" :severity="severityVariant(detail.severity)" />
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</div>
                        <div class="mt-1 text-sm text-slate-800">{{ detail.category }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Occurred At</div>
                        <div class="mt-1 text-sm text-slate-800">{{ detail.occurredAt }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Actor</div>
                        <div class="mt-1 text-sm text-slate-800">{{ actorText(detail.actor) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</div>
                        <div class="mt-1 text-sm text-slate-800">{{ subjectText(detail.subject) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client</div>
                        <div class="mt-1 text-sm text-slate-800">{{ clientText(detail.client) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">IP Address</div>
                        <div class="mt-1 text-sm text-slate-800">{{ detail.ipAddress || "N/A" }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">User Agent</div>
                        <div class="mt-1 text-sm text-slate-800 break-all">{{ detail.userAgent || "N/A" }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request ID</div>
                        <div class="mt-1 text-sm text-slate-800 break-all">{{ detail.requestId || "N/A" }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Summary</div>
                    <p class="mt-1 text-sm text-slate-800">
                        {{ summaryText(detail.summary) }}
                    </p>
                </div>

                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sanitized Meta</div>
                    <pre class="mt-2 max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100" data-audit-meta>{{ formattedMeta }}</pre>
                </div>

                <div class="flex justify-end">
                    <Button label="Close" severity="secondary" outlined @click="closeDetail" />
                </div>
            </div>
        </Dialog>

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                title="Audit Logs"
                description="Append-only review trail for authentication, authorization, and administrative activity."
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
                                    placeholder="Search event, actor, summary, or IP"
                                    @keyup.enter="onGlobalSearch"
                                />
                            </IconField>
                        </template>
                    </AdminTableToolbar>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <InputText
                            v-model="tableFilters.eventType.value"
                            placeholder="Event type"
                            @keyup.enter="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.category.value"
                            :options="categoryOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Category"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.severity.value"
                            :options="severityOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Severity"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.clientId.value"
                            :options="clientOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Client"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.actorType.value"
                            :options="actorTypeOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Actor type"
                            @change="onTableFilter"
                        />
                        <Select
                            v-model="tableFilters.subjectType.value"
                            :options="subjectTypeOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Subject type"
                            @change="onTableFilter"
                        />
                        <InputText
                            v-model="tableFilters.dateFrom.value"
                            type="date"
                            placeholder="Date from"
                            @change="onTableFilter"
                        />
                        <InputText
                            v-model="tableFilters.dateTo.value"
                            type="date"
                            placeholder="Date to"
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
                            <Column field="id" header="ID" sortable />

                            <Column field="occurred_at" header="Occurred" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.occurredAt }}</span>
                                </template>
                            </Column>

                            <Column field="event_type" header="Event" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-800">{{ data.eventType }}</span>
                                        <span class="text-sm text-slate-500">{{ summaryText(data.summary) }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="category" header="Category" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.category }}</span>
                                </template>
                            </Column>

                            <Column field="severity" header="Severity" sortable>
                                <template #body="{ data }">
                                    <Tag :value="severityLabel(data.severity)" :severity="severityVariant(data.severity)" />
                                </template>
                            </Column>

                            <Column header="Actor">
                                <template #body="{ data }">
                                    <span>{{ actorText(data.actor) }}</span>
                                </template>
                            </Column>

                            <Column header="Subject">
                                <template #body="{ data }">
                                    <span>{{ subjectText(data.subject) }}</span>
                                </template>
                            </Column>

                            <Column header="Client">
                                <template #body="{ data }">
                                    <span>{{ clientText(data.client) }}</span>
                                </template>
                            </Column>

                            <Column header="IP Address">
                                <template #body="{ data }">
                                    <span>{{ data.ipAddress || "N/A" }}</span>
                                </template>
                            </Column>

                            <Column header="Actions">
                                <template #body="{ data }">
                                    <RowActionMenu :items="resolveRowActions(data)" :disabled="detailBusy" />
                                </template>
                            </Column>

                            <template #empty>
                                <div class="flex min-h-40 items-center justify-center text-slate-500">
                                    No audit logs match the current filters.
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
