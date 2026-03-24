<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { reactive, ref } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    pagination: {
        type: Object,
        required: true,
    },
    sorting: {
        type: Object,
        required: true,
    },
});

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
    email: { value: props.filters.email ?? null, matchMode: FilterMatchMode.CONTAINS },
    emailVerifiedAt: { value: props.filters.verified ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? 'name',
    sortOrder: props.sorting.order ?? 1,
});

const perPageOptions = [5, 10, 15, 25];

const verifiedOptions = [
    { label: 'All', value: null },
    { label: 'Verified', value: 'verified' },
    { label: 'Pending', value: 'pending' },
];

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    name: tableFilters.value.name.value || undefined,
    email: tableFilters.value.email.value || undefined,
    verified: tableFilters.value.emailVerifiedAt.value || undefined,
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const reload = (overrides = {}) => {
    router.get(route('admin.users.index'), buildParams(overrides), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
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
        email: event.filters.email?.value || undefined,
        verified: event.filters.emailVerifiedAt?.value || undefined,
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
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <PageHeader
            title="Users"
            description="Example read flow using Controller -> Service -> Repository -> Data. This page is intentionally simple but production-oriented for future user and operator modules."
        />

        <Card class="surface-card">
            <template #content>
                <DataTable
                    :value="rows"
                    v-model:filters="tableFilters"
                    :rows="tableState.perPage"
                    :first="pagination.first"
                    :totalRecords="pagination.total"
                    :rowsPerPageOptions="perPageOptions"
                    :sortField="tableState.sortField"
                    :sortOrder="tableState.sortOrder"
                    data-key="id"
                    paginator
                    lazy
                    striped-rows
                    filterDisplay="menu"
                    removableSort
                    responsive-layout="scroll"
                    @filter="onFilter"
                    @sort="onSort"
                    @page="onPage"
                >
                    <template #header>
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <IconField class="w-full sm:max-w-sm">
                                <InputIcon class="pi pi-search text-slate-400" />
                                <InputText
                                    v-model="tableFilters.global.value"
                                    placeholder="Global search"
                                    class="w-full"
                                    @update:modelValue="onGlobalFilterInput"
                                />
                            </IconField>

                            <Button
                                label="Repository-backed read model"
                                icon="pi pi-database"
                                severity="secondary"
                                outlined
                            />
                        </div>
                    </template>

                    <Column
                        field="name"
                        header="Name"
                        sortable
                        :showFilterMatchModes="false"
                        :showFilterOperator="false"
                        :showAddButton="false"
                    >
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText
                                v-model="filterModel.value"
                                placeholder="Filter name"
                                class="w-full"
                                @input="filterCallback()"
                            />
                        </template>
                    </Column>
                    <Column
                        field="email"
                        header="Email"
                        sortable
                        :showFilterMatchModes="false"
                        :showFilterOperator="false"
                        :showAddButton="false"
                    >
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText
                                v-model="filterModel.value"
                                placeholder="Filter email"
                                class="w-full"
                                @input="filterCallback()"
                            />
                        </template>
                    </Column>
                    <Column header="Roles">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="role in data.roles"
                                    :key="role"
                                    :value="role"
                                    severity="info"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="emailVerifiedAt"
                        header="Verified"
                        sortable
                        :showFilterMatchModes="false"
                        :showFilterOperator="false"
                        :showAddButton="false"
                    >
                        <template #body="{ data }">
                            <Tag :value="data.emailVerifiedAt ? 'Verified' : 'Pending'" :severity="data.emailVerifiedAt ? 'success' : 'warn'" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <Select
                                v-model="filterModel.value"
                                :options="verifiedOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All"
                                class="w-full"
                                @change="filterCallback()"
                            />
                        </template>
                    </Column>
                    <Column field="createdAt" header="Created At" sortable />
                </DataTable>

                <div class="mt-5 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} users
                    </div>
                    <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
