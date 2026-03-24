<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { onMounted, reactive, ref } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            global: null,
            name: null,
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
    canManageRoles: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();

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

const reload = (overrides = {}) => {
    router.get(route('admin.roles.index'), buildParams(overrides), {
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

const visitCreate = () => {
    router.get(route('admin.roles.create'));
};

const visitEdit = (role) => {
    router.get(route('admin.roles.edit', role.id));
};

const permissionTags = (role) => {
    if (Array.isArray(role.permissions) && role.permissions.length) {
        return role.permissions;
    }

    return [];
};

onMounted(() => {
    if (page.props.flash?.success) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: page.props.flash.success,
            life: 3000,
        });
    }

    if (page.props.flash?.error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: page.props.flash.error,
            life: 4000,
        });
    }
});
</script>

<template>
    <Head title="Roles" />

    <AuthenticatedLayout>
        <Toast />

        <PageHeader
            title="Roles"
            description="Manage application roles with the same admin list flow used in the Users module."
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
                                v-if="canManageRoles"
                                label="Create Role"
                                icon="pi pi-plus"
                                @click="visitCreate"
                            />
                        </div>
                    </template>

                    <template #empty>
                        <div class="py-8 text-center text-sm text-slate-500">
                            No roles found for the current filters.
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

                    <Column header="Permissions">
                        <template #body="{ data }">
                            <div v-if="permissionTags(data).length" class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="permission in permissionTags(data)"
                                    :key="permission"
                                    :value="permission"
                                    severity="info"
                                />
                            </div>
                            <span v-else class="text-sm text-slate-500">
                                {{ data.permissionsCount ?? 0 }} permissions
                            </span>
                        </template>
                    </Column>

                    <Column field="createdAt" header="Created At" sortable />

                    <Column v-if="canManageRoles" header="Actions" :exportable="false" style="width: 8rem">
                        <template #body="{ data }">
                            <Button
                                label="Edit"
                                icon="pi pi-pencil"
                                size="small"
                                outlined
                                @click="visitEdit(data)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-5 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} roles
                    </div>
                    <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
