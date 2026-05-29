<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";
import Button from "primevue/button";
import Column from "primevue/column";
import DatePicker from "primevue/datepicker";
import Dialog from "primevue/dialog";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
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
    eventOptions: {
        type: Array,
        default: () => [],
    },
    severityOptions: {
        type: Array,
        default: () => [],
    },
});

const busy = ref(false);
const detailVisible = ref(false);
const selectedLog = ref(null);
const rows = computed(() => props.rows);

const tableFilters = ref({
    search: { value: props.filters.search ?? null, matchMode: FilterMatchMode.CONTAINS },
    event: { value: props.filters.event ?? null, matchMode: FilterMatchMode.EQUALS },
    severity: { value: props.filters.severity ?? null, matchMode: FilterMatchMode.EQUALS },
    dateFrom: { value: props.filters.date_from ?? null, matchMode: FilterMatchMode.EQUALS },
    dateTo: { value: props.filters.date_to ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage ?? 1,
    perPage: props.pagination.perPage ?? 15,
    sortField: props.sorting.field ?? "created_at",
    sortOrder: props.sorting.order ?? -1,
});

const eventOptions = computed(() => [
    { label: trans("common.all"), value: null },
    ...props.eventOptions,
]);

const severityOptions = computed(() => [
    { label: trans("common.all"), value: null },
    { label: trans("audit_logs.severity.info"), value: "info" },
    { label: trans("audit_logs.severity.warning"), value: "warning" },
    { label: trans("audit_logs.severity.error"), value: "error" },
]);

const buildParams = (overrides = {}) => ({
    search: tableFilters.value.search.value || undefined,
    event: tableFilters.value.event.value || undefined,
    severity: tableFilters.value.severity.value || undefined,
    date_from: tableFilters.value.dateFrom.value || undefined,
    date_to: tableFilters.value.dateTo.value || undefined,
    page: tableState.page,
    per_page: tableState.perPage,
    sort_field: tableState.sortField || undefined,
    sort_order: tableState.sortOrder || undefined,
    ...overrides,
});

const reload = (overrides = {}) => {
    busy.value = true;

    router.get(route("admin.audit-logs.index"), buildParams(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            busy.value = false;
        },
    });
};

const applyFilters = () => {
    tableState.page = 1;
    reload({ page: 1 });
};

const resetFilters = () => {
    tableFilters.value.search.value = null;
    tableFilters.value.event.value = null;
    tableFilters.value.severity.value = null;
    tableFilters.value.dateFrom.value = null;
    tableFilters.value.dateTo.value = null;
    tableState.page = 1;
    tableState.sortField = "created_at";
    tableState.sortOrder = -1;
    reload({
        search: undefined,
        event: undefined,
        severity: undefined,
        date_from: undefined,
        date_to: undefined,
        page: 1,
        sort_field: "created_at",
        sort_order: -1,
    });
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;
    reload({ page: tableState.page, per_page: tableState.perPage });
};

const onSort = (event) => {
    tableState.sortField = event.sortField || "created_at";
    tableState.sortOrder = event.sortOrder || -1;
    reload({
        sort_field: tableState.sortField,
        sort_order: tableState.sortOrder,
    });
};

const openDetails = (row) => {
    selectedLog.value = row;
    detailVisible.value = true;
};

const closeDetails = () => {
    detailVisible.value = false;
    selectedLog.value = null;
};

const severityLabel = (severity) => trans(`audit_logs.severity.${severity || "info"}`);

const severitySeverity = (severity) => ({
    error: "danger",
    warning: "warn",
    info: "info",
}[severity] || "secondary");

const prettyJson = (value) => JSON.stringify(value ?? {}, null, 2);

const actorLabel = (row) => row.actor?.label || trans("common.system");
const clientLabel = (row) => row.client?.label || trans("common.not_available");
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="trans('audit_logs.title')" />

        <Dialog
            :visible="detailVisible"
            modal
            :header="trans('audit_logs.details.title')"
            class="w-[min(900px,95vw)]"
            @update:visible="detailVisible = $event"
        >
            <div v-if="selectedLog" class="flex max-h-[75vh] flex-col gap-5 overflow-y-auto" data-audit-details-dialog>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.event") }}</p>
                        <p class="mt-1 break-all text-sm font-medium text-slate-900">{{ selectedLog.event }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.severity") }}</p>
                        <Tag class="mt-1" :value="severityLabel(selectedLog.severity)" :severity="severitySeverity(selectedLog.severity)" />
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.actor") }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ actorLabel(selectedLog) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.client") }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ clientLabel(selectedLog) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.ip_address") }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedLog.ipAddress || trans("common.not_available") }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.created_at") }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedLog.createdAt }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.user_agent") }}</p>
                    <p class="mt-1 break-words text-sm text-slate-700">{{ selectedLog.userAgent || trans("common.not_available") }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <p class="text-xs font-semibold uppercase text-slate-400">{{ trans("audit_logs.fields.description") }}</p>
                    <p class="mt-1 break-words text-sm text-slate-700">{{ selectedLog.description || trans("common.not_available") }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-900">{{ trans("audit_logs.details.properties") }}</p>
                        <pre class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-xs leading-5 text-slate-100">{{ prettyJson(selectedLog.properties) }}</pre>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-900">{{ trans("audit_logs.details.context") }}</p>
                        <pre class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-xs leading-5 text-slate-100">{{ prettyJson(selectedLog.context) }}</pre>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button :label="trans('common.close')" severity="secondary" outlined data-audit-details-close @click="closeDetails" />
                </div>
            </div>
        </Dialog>

        <div class="flex h-full min-h-0 flex-1 flex-col gap-6">
            <PageHeader
                :title="trans('audit_logs.title')"
                :description="trans('audit_logs.description')"
            />

            <AdminTableCard>
                <div class="flex min-h-0 flex-1 flex-col gap-4 p-6">
                    <AdminTableToolbar
                        :busy="busy"
                        :can-create="false"
                        :can-bulk-delete="false"
                        :selected-count="0"
                        :selectable-count="0"
                        @refresh="reload"
                    >
                        <template #search>
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="tableFilters.search.value"
                                    class="w-full"
                                    :placeholder="trans('audit_logs.search_placeholder')"
                                    data-audit-search
                                    @keyup.enter="applyFilters"
                                />
                            </IconField>
                        </template>
                    </AdminTableToolbar>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <Select
                            v-model="tableFilters.event.value"
                            :options="eventOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('audit_logs.filters.event')"
                            data-audit-event-filter
                            @change="applyFilters"
                        />
                        <Select
                            v-model="tableFilters.severity.value"
                            :options="severityOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="trans('audit_logs.filters.severity')"
                            data-audit-severity-filter
                            @change="applyFilters"
                        />
                        <DatePicker
                            v-model="tableFilters.dateFrom.value"
                            :placeholder="trans('audit_logs.filters.date_from')"
                            date-format="yy-mm-dd"
                            show-icon
                            data-audit-date-from
                            @date-select="applyFilters"
                            @change="applyFilters"
                        />
                        <DatePicker
                            v-model="tableFilters.dateTo.value"
                            :placeholder="trans('audit_logs.filters.date_to')"
                            date-format="yy-mm-dd"
                            show-icon
                            data-audit-date-to
                            @date-select="applyFilters"
                            @change="applyFilters"
                        />
                        <Button
                            :label="trans('audit_logs.filters.reset')"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            data-audit-reset
                            @click="resetFilters"
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
                            :loading="busy"
                            :rows="pagination.perPage"
                            :first="pagination.first"
                            :total-records="pagination.total"
                            :sort-field="tableState.sortField"
                            :sort-order="tableState.sortOrder"
                            :empty-message="trans('audit_logs.empty_message')"
                            :always-show-paginator="true"
                            @page="onPage"
                            @sort="onSort"
                        >
                            <Column field="created_at" :header="trans('audit_logs.fields.created_at')" sortable>
                                <template #body="{ data }">
                                    <span>{{ data.createdAt }}</span>
                                </template>
                            </Column>

                            <Column field="event" :header="trans('audit_logs.fields.event')" sortable>
                                <template #body="{ data }">
                                    <span class="break-all font-medium text-slate-900">{{ data.event }}</span>
                                </template>
                            </Column>

                            <Column field="severity" :header="trans('audit_logs.fields.severity')" sortable>
                                <template #body="{ data }">
                                    <Tag :value="severityLabel(data.severity)" :severity="severitySeverity(data.severity)" />
                                </template>
                            </Column>

                            <Column field="actor_id" :header="trans('audit_logs.fields.actor')" sortable>
                                <template #body="{ data }">
                                    <span>{{ actorLabel(data) }}</span>
                                </template>
                            </Column>

                            <Column field="client_id" :header="trans('audit_logs.fields.client')" sortable>
                                <template #body="{ data }">
                                    <span>{{ clientLabel(data) }}</span>
                                </template>
                            </Column>

                            <Column field="ipAddress" :header="trans('audit_logs.fields.ip_address')">
                                <template #body="{ data }">
                                    <span>{{ data.ipAddress || trans("common.not_available") }}</span>
                                </template>
                            </Column>

                            <Column field="userAgentShort" :header="trans('audit_logs.fields.user_agent')">
                                <template #body="{ data }">
                                    <span class="block max-w-xs truncate">{{ data.userAgentShort || trans("common.not_available") }}</span>
                                </template>
                            </Column>

                            <Column :header="trans('table.columns.actions')" :style="{ width: '10rem' }">
                                <template #body="{ data }">
                                    <Button
                                        :label="trans('audit_logs.actions.details')"
                                        icon="pi pi-eye"
                                        size="small"
                                        outlined
                                        data-audit-details
                                        @click="openDetails(data)"
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
