<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { Head, router, usePage } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    tokenPolicies: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            global: null,
            name: null,
            status: null,
        }),
    },
    pagination: {
        type: Object,
        default: () => ({
            currentPage: 1,
            lastPage: 1,
            perPage: 10,
            total: 0,
            from: 0,
            to: 0,
            first: 0,
        }),
    },
    sorting: {
        type: Object,
        default: () => ({
            field: 'name',
            order: 1,
        }),
    },
    canManageClients: {
        type: Boolean,
        default: false,
    },
});

const toast = useToast();
const page = usePage();
const rows = computed(() => props.rows);

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
});

const tableState = reactive({
    page: props.pagination.currentPage,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? 'name',
    sortOrder: props.sorting.order ?? 1,
});

const perPageOptions = [5, 10, 15, 25];

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    name: tableFilters.value.name.value || undefined,
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const {
    busy,
    refresh,
    confirmDelete,
} = useAdminListActions({
    indexRouteName: 'admin.sso-clients.index',
    destroyRouteName: 'admin.sso-clients.destroy',
    bulkDestroyRouteName: 'admin.sso-clients.destroy',
    entityLabel: 'SSO Client',
    entityLabelPlural: 'clients',
    buildParams,
    clearSelection: () => {},
    selectedIds: ref([]),
});

const flashClientSecret = computed(() => page.props.flash?.clientSecret ?? null);

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

const reload = (overrides = {}) => {
    router.get(route('admin.sso-clients.index'), buildParams(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    tableState.page = 1;
    reload({
        page: 1,
        global: value || undefined,
    });
};

const onFilter = (event) => {
    tableState.page = 1;

    reload({
        page: 1,
        global: event.filters.global?.value || undefined,
        name: event.filters.name?.value || undefined,
    });
};

const onSort = (event) => {
    tableState.sortField = event.sortField;
    tableState.sortOrder = event.sortOrder;

    reload({
        sortField: event.sortField,
        sortOrder: event.sortOrder,
    });
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;

    reload({
        page: event.page + 1,
        perPage: event.rows,
    });
};

const goToCreatePage = () => {
    router.get(route('admin.sso-clients.create'));
};

const goToEditPage = (client) => {
    router.get(route('admin.sso-clients.edit', client.id));
};

const clientActionItems = (client) => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        command: () => goToEditPage(client),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        command: () => confirmDelete(client),
    },
];
</script>

<template>
    <Head title="SSO Clients" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="SSO Clients"
                description="Manage client registrations with dedicated create and edit pages for a stable, scalable SSO admin flow."
            />

            <div
                v-if="flashClientSecret"
                class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-950"
            >
                <div class="font-semibold">Client secret</div>
                <p class="mt-1 leading-6 text-emerald-900">
                    Save this secret now. It will not be shown again after this request.
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

            <AdminTableCard>
                <div class="admin-table-shell">
                    <DataTable
                        :value="rows"
                        v-model:filters="tableFilters"
                        :rows="tableState.perPage"
                        :first="pagination.first"
                        :totalRecords="pagination.total"
                        :rowsPerPageOptions="perPageOptions"
                        :sortField="tableState.sortField"
                        :sortOrder="tableState.sortOrder"
                        :loading="busy"
                        class="admin-datatable h-full"
                        data-key="id"
                        paginator
                        lazy
                        scrollable
                        scrollHeight="flex"
                        striped-rows
                        filterDisplay="menu"
                        removableSort
                        responsive-layout="scroll"
                        @filter="onFilter"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManageClients"
                                createLabel="Create Client"
                                :busy="busy"
                                @create="goToCreatePage"
                                @refresh="refresh"
                            >
                                <template #search>
                                    <IconField class="w-full">
                                        <InputIcon class="pi pi-search text-slate-400" />
                                        <InputText
                                            v-model="tableFilters.global.value"
                                            placeholder="Search clients"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                No clients found for the current filters.
                            </div>
                        </template>

                        <Column field="name" header="Name" sortable />
                        <Column field="clientId" header="Client ID" sortable />
                        <Column field="isActive" header="Status">
                            <template #body="{ data }">
                                <Tag :value="data.isActive ? 'Active' : 'Inactive'" :severity="data.isActive ? 'success' : 'warn'" />
                            </template>
                        </Column>
                        <Column field="redirectUriCount" header="Redirect URIs">
                            <template #body="{ data }">
                                <div class="space-y-1">
                                    <div class="font-medium text-slate-700">{{ data.redirectUriCount }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ data.redirectUris[0] ?? 'No redirect URI configured' }}
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column field="scopesCount" header="Scopes">
                            <template #body="{ data }">
                                <div class="flex flex-wrap gap-2">
                                    <Tag
                                        v-for="scope in data.scopes.slice(0, 3)"
                                        :key="scope"
                                        :value="scope"
                                        severity="secondary"
                                    />
                                    <span v-if="data.scopes.length > 3" class="text-xs text-slate-500">
                                        +{{ data.scopes.length - 3 }} more
                                    </span>
                                </div>
                            </template>
                        </Column>
                        <Column field="createdAt" header="Created At" sortable />

                        <Column v-if="canManageClients" header="Actions" :exportable="false" style="width: 5rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="clientActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} clients
                        </div>
                        <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
