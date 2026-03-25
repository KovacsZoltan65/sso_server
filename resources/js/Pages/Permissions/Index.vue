<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreateModal from '@/Pages/Permissions/CreateModal.vue';
import EditModal from '@/Pages/Permissions/EditModal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { reactive, ref, watch } from 'vue';

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
    canManagePermissions: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();
const confirm = useConfirm();

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedPermission = ref(null);

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
    router.get(route('admin.permissions.index'), buildParams(overrides), {
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

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (permission) => {
    selectedPermission.value = permission;
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedPermission.value = null;
};

const handleEditVisibilityChange = (value) => {
    if (value) {
        isEditModalOpen.value = true;
        return;
    }

    closeEditModal();
};

const handleSaved = ({ message }) => {
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000,
    });

    closeEditModal();
    isCreateModalOpen.value = false;
    reload();
};

const confirmDelete = (permission) => {
    confirm.require({
        message: `Delete "${permission.name}"? This action cannot be undone.`,
        header: 'Delete Permission',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('admin.permissions.destroy', permission.id), {
                preserveScroll: true,
                onSuccess: () => {
                    const success = page.props.flash?.success ?? 'Permission deleted successfully.';
                    const error = page.props.flash?.error;

                    if (error) {
                        toast.add({
                            severity: 'error',
                            summary: 'Error',
                            detail: error,
                            life: 4000,
                        });

                        return;
                    }

                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: success,
                        life: 3000,
                    });
                },
            });
        },
    });
};

watch(
    () => page.props.flash?.error,
    (error) => {
        if (!error) {
            return;
        }

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error,
            life: 4000,
        });
    },
);
</script>

<template>
    <Head title="Permissions" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <PageHeader
            title="Permissions"
            description="Manage application permissions with the same admin list flow used in the Users module."
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
                                v-if="canManagePermissions"
                                label="Create Permission"
                                icon="pi pi-plus"
                                @click="openCreateModal"
                            />
                        </div>
                    </template>

                    <template #empty>
                        <div class="py-8 text-center text-sm text-slate-500">
                            No permissions found for the current filters.
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

                    <Column field="guardName" header="Guard">
                        <template #body="{ data }">
                            <Tag :value="data.guardName" severity="secondary" />
                        </template>
                    </Column>

                    <Column field="rolesCount" header="Assigned Roles" />
                    <Column field="createdAt" header="Created At" sortable />

                    <Column v-if="canManagePermissions" header="Actions" :exportable="false" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Button
                                    label="Edit"
                                    icon="pi pi-pencil"
                                    size="small"
                                    outlined
                                    @click="openEditModal(data)"
                                />
                                <Button
                                    label="Delete"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    text
                                    @click="confirmDelete(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-5 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} permissions
                    </div>
                    <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                </div>
            </template>
        </Card>

        <CreateModal
            v-model:visible="isCreateModalOpen"
            @saved="handleSaved"
        />

        <EditModal
            v-model:visible="isEditModalOpen"
            :permission="selectedPermission"
            @saved="handleSaved"
            @update:visible="handleEditVisibilityChange"
        />
    </AuthenticatedLayout>
</template>
