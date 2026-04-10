<script setup>
defineOptions({
    inheritAttrs: false,
});

import { adminCurrentPageReportTemplate, adminPaginatorTemplate, adminRowsPerPageOptions } from '@/Constants/adminTablePagination';
import DataTable from 'primevue/datatable';
import { computed, useAttrs } from 'vue';

const props = defineProps({
    value: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    loadingMessage: {
        type: String,
        default: 'Loading data...',
    },
    rows: {
        type: Number,
        default: 10,
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
    first: {
        type: Number,
        default: 0,
    },
    lazy: {
        type: Boolean,
        default: true,
    },
    paginator: {
        type: Boolean,
        default: true,
    },
    alwaysShowPaginator: {
        type: Boolean,
        default: true,
    },
    rowsPerPageOptions: {
        type: Array,
        default: () => adminRowsPerPageOptions,
    },
    paginatorTemplate: {
        type: String,
        default: adminPaginatorTemplate,
    },
    currentPageReportTemplate: {
        type: String,
        default: adminCurrentPageReportTemplate,
    },
    dataKey: {
        type: String,
        default: 'id',
    },
    scrollable: {
        type: Boolean,
        default: true,
    },
    scrollHeight: {
        type: String,
        default: 'flex',
    },
    stripedRows: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: null,
    },
    emptyMessage: {
        type: String,
        default: 'No records found.',
    },
    rowClass: {
        type: [Function, String, Array, Object],
        default: null,
    },
    tableClass: {
        type: [String, Array, Object],
        default: '',
    },
    wrapperClass: {
        type: [String, Array, Object],
        default: '',
    },
});

const emit = defineEmits(['page', 'sort', 'filter', 'row-click', 'update:filters']);

const attrs = useAttrs();

const normalizedRowsPerPageOptions = computed(() => {
    if (Array.isArray(props.rowsPerPageOptions) && props.rowsPerPageOptions.length > 0) {
        return props.rowsPerPageOptions;
    }

    return [props.rows].filter(Boolean);
});

const normalizedTotalRecords = computed(() => {
    const total = Number(props.totalRecords);

    return Number.isFinite(total) && total >= 0 ? total : 0;
});

const normalizedFirst = computed(() => {
    const first = Number(props.first);

    return Number.isFinite(first) && first >= 0 ? first : 0;
});

const normalizedTableClass = computed(() => ['admin-datatable h-full', props.tableClass]);
const normalizedWrapperClass = computed(() => ['base-data-table flex min-h-0 flex-1 flex-col overflow-hidden', props.wrapperClass]);

const emitPage = (event) => emit('page', event);
const emitSort = (event) => emit('sort', event);
const emitFilter = (event) => emit('filter', event);
const emitRowClick = (event) => emit('row-click', event);
const emitFiltersUpdate = (value) => emit('update:filters', value);
</script>

<template>
    <div :class="normalizedWrapperClass">
        <slot name="filters" />

        <DataTable
            v-bind="attrs"
            :value="value"
            :filters="filters"
            :loading="loading"
            :rows="rows"
            :first="normalizedFirst"
            :totalRecords="normalizedTotalRecords"
            :rowsPerPageOptions="normalizedRowsPerPageOptions"
            :lazy="lazy"
            :paginator="paginator"
            :alwaysShowPaginator="alwaysShowPaginator"
            :paginatorTemplate="paginatorTemplate"
            :currentPageReportTemplate="currentPageReportTemplate"
            :data-key="dataKey"
            :scrollable="scrollable"
            :scrollHeight="scrollHeight"
            :stripedRows="stripedRows"
            :size="size"
            :rowClass="rowClass"
            :class="normalizedTableClass"
            @page="emitPage"
            @sort="emitSort"
            @filter="emitFilter"
            @row-click="emitRowClick"
            @update:filters="emitFiltersUpdate"
        >
            <template v-if="$slots.header || $slots.actions" #header>
                <div class="flex flex-col gap-4">
                    <slot name="header" />
                    <slot name="actions" />
                </div>
            </template>

            <template #loading>
                <slot name="loading">
                    <div class="flex items-center gap-3 px-6 py-8 text-sm text-slate-500">
                        <i class="pi pi-spin pi-spinner text-base" />
                        <span>{{ loadingMessage }}</span>
                    </div>
                </slot>
            </template>

            <template #empty>
                <slot name="empty">
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        {{ emptyMessage }}
                    </div>
                </slot>
            </template>

            <slot />
        </DataTable>
    </div>
</template>
