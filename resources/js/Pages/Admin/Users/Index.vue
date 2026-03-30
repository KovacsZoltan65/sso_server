<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminListActions } from "@/Composables/useAdminListActions";
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";
import CreateModal from "@/Pages/Admin/Users/CreateModal.vue";
import EditModal from "@/Pages/Admin/Users/EditModal.vue";
import { Head } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import Checkbox from "primevue/checkbox";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
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
    roleOptions: {
        type: Array,
        default: () => [],
    },
    canManageUsers: {
        type: Boolean,
        default: false,
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

const rows = computed(() => props.rows);

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedUser = ref(null);

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
    email: { value: props.filters.email ?? null, matchMode: FilterMatchMode.CONTAINS },
    emailVerifiedAt: {
        value: props.filters.verified ?? null,
        matchMode: FilterMatchMode.EQUALS,
    },
});

const tableState = reactive({
    page: props.pagination.currentPage,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? "name",
    sortOrder: props.sorting.order ?? 1,
});

const perPageOptions = [5, 10, 15, 25];

const verifiedOptions = [
    { label: "All", value: null },
    { label: "Verified", value: "verified" },
    { label: "Pending", value: "pending" },
];

const {
    selectedIds,
    selectedRows,
    selectableRows,
    allSelected,
    partiallySelected,
    clearSelection,
    toggleRowSelection,
    toggleAllSelection,
} = useAdminTableSelection(rows);

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

const {
    busy,
    showSuccess,
    reload,
    refresh,
    confirmDelete,
    confirmBulkDelete,
} = useAdminListActions({
    indexRouteName: "admin.users.index",
    destroyRouteName: "admin.users.destroy",
    bulkDestroyRouteName: "admin.users.bulk-destroy",
    entityLabel: "User",
    entityLabelPlural: "users",
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    tableState.page = 1;
    reload(
        {
            page: 1,
            global: value || undefined,
        },
        { resetSelection: true }
    );
};

const onFilter = (event) => {
    tableState.page = 1;

    reload(
        {
            page: 1,
            global: event.filters.global?.value || undefined,
            name: event.filters.name?.value || undefined,
            email: event.filters.email?.value || undefined,
            verified: event.filters.emailVerifiedAt?.value || undefined,
        },
        { resetSelection: true }
    );
};

const onSort = (event) => {
    tableState.sortField = event.sortField;
    tableState.sortOrder = event.sortOrder;

    reload(
        {
            sortField: event.sortField,
            sortOrder: event.sortOrder,
        },
        { resetSelection: true }
    );
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;

    reload(
        {
            page: event.page + 1,
            perPage: event.rows,
        },
        { resetSelection: true }
    );
};

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (user) => {
    selectedUser.value = user;
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedUser.value = null;
};

const closeAllOverlays = () => {
    isCreateModalOpen.value = false;
    closeEditModal();
};

usePageOverlayCleanup(closeAllOverlays);

const handleEditVisibilityChange = (value) => {
    if (value) {
        isEditModalOpen.value = true;
        return;
    }

    closeEditModal();
};

const handleSaved = ({ message }) => {
    showSuccess(message);
    clearSelection();
    closeAllOverlays();
    reload({}, { resetSelection: true });
};

const userActionItems = (user) => [
    {
        label: "Edit",
        icon: "pi pi-pencil",
        command: () => openEditModal(user),
    },
    {
        label: "Delete",
        icon: "pi pi-trash",
        disabled: !user.canDelete,
        command: () => confirmDelete(user),
    },
];
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Users"
                description="Repository-backed admin user list with consistent selection, row actions, bulk delete, and refresh behavior."
            />

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
                                :canCreate="canManageUsers"
                                createLabel="Create User"
                                :canBulkDelete="canManageUsers"
                                bulkDeleteLabel="Delete Selected"
                                :selectedCount="selectedRows.length"
                                :selectableCount="selectableRows.length"
                                :busy="busy"
                                @create="openCreateModal"
                                @bulk-delete="confirmBulkDelete"
                                @refresh="refresh"
                            >
                                <template #search>
                                    <IconField class="w-full">
                                        <InputIcon class="pi pi-search text-slate-400" />
                                        <InputText
                                            v-model="tableFilters.global.value"
                                            placeholder="Global search"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                No users found for the current filters.
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div
                                    :title="
                                        selectableRows.length === 0
                                            ? 'No deletable users on this page.'
                                            : ''
                                    "
                                >
                                    <Checkbox
                                        :binary="true"
                                        :modelValue="allSelected"
                                        :indeterminate="partiallySelected"
                                        :disabled="selectableRows.length === 0"
                                        @update:modelValue="toggleAllSelection"
                                    />
                                </div>
                            </template>

                            <template #body="{ data }">
                                <div :title="data.deleteBlockReason ?? ''">
                                    <Checkbox
                                        :binary="true"
                                        :modelValue="selectedIds.includes(data.id)"
                                        :disabled="!data.canDelete"
                                        @update:modelValue="toggleRowSelection(data)"
                                    />
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="name"
                            header="Name"
                            sortable
                            :showFilterMatchModes="false"
                            :showFilterOperator="false"
                            :showAddButton="false"
                        >
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span>{{ data.name }}</span>
                                    <Tag
                                        v-if="data.deleteBlockCode === 'current_user'"
                                        value="You"
                                        severity="contrast"
                                    />
                                    <Tag
                                        v-if="data.deleteBlockCode === 'protected_user'"
                                        value="Protected"
                                        severity="warn"
                                    />
                                </div>
                            </template>

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
                                <Tag
                                    :value="data.emailVerifiedAt ? 'Verified' : 'Pending'"
                                    :severity="data.emailVerifiedAt ? 'success' : 'warn'"
                                />
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

                        <Column
                            v-if="canManageUsers"
                            header="Actions"
                            :exportable="false"
                            style="width: 5rem"
                        >
                            <template #body="{ data }">
                                <RowActionMenu :items="userActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div
                        class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of
                            {{ pagination.total }} users
                        </div>
                        <div>
                            Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                        </div>
                    </div>
                </template>
            </AdminTableCard>
        </div>

        <CreateModal
            v-model:visible="isCreateModalOpen"
            :roleOptions="roleOptions"
            @saved="handleSaved"
        />

        <EditModal
            v-model:visible="isEditModalOpen"
            :user="selectedUser"
            :roleOptions="roleOptions"
            @saved="handleSaved"
            @update:visible="handleEditVisibilityChange"
        />
    </AuthenticatedLayout>
</template>
