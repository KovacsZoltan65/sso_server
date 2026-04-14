<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { trans } from 'laravel-vue-i18n';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
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
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { computed, ref } from "vue";

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

const {
    state: tableState,
    filters: tableFilters,
    first,
    lastPage,
    resetPagination,
    setPageFromEvent,
    setSortFromEvent,
    buildFetchParams,
} = useAdminTableState({
    initialPage: props.pagination.currentPage,
    initialPerPage: props.pagination.perPage ?? 10,
    initialSortField: props.sorting.field ?? "name",
    initialSortOrder: props.sorting.order ?? 1,
    initialTotalRecords: props.pagination.total ?? 0,
    initialFilters: {
        global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
        name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
        email: { value: props.filters.email ?? null, matchMode: FilterMatchMode.CONTAINS },
        isActive: {
            value: props.filters.status ?? null,
            matchMode: FilterMatchMode.EQUALS,
        },
        emailVerifiedAt: {
            value: props.filters.verified ?? null,
            matchMode: FilterMatchMode.EQUALS,
        },
    },
    serializeSortOrder: (value) => value,
});

const perPageOptions = [5, 10, 15, 25];

const verifiedOptions = [
    { label: "All", value: null },
    { label: "Verified", value: "verified" },
    { label: "Pending", value: "pending" },
];

const statusOptions = [
    { label: "All", value: null },
    { label: "Active", value: "active" },
    { label: "Inactive", value: "inactive" },
];

const {
    selectedIds,
    selectedCount,
    selectableCount,
    allSelected,
    partiallySelected,
    clearSelection: clearSelectionRows,
    toggleRowSelection,
    toggleAllSelection,
} = useAdminTableSelection(rows);

const clearTableSelection = () => {
    clearSelectionRows();
};

const buildParams = (overrides = {}) => buildFetchParams({
    filters: {
        global: tableFilters.global.value || undefined,
        name: tableFilters.name.value || undefined,
        email: tableFilters.email.value || undefined,
        status: tableFilters.isActive.value || undefined,
        verified: tableFilters.emailVerifiedAt.value || undefined,
    },
    extra: overrides,
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
    clearSelection: clearTableSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.global.value = value ?? null;
    resetPagination();
    reload(buildParams({ page: 1, global: value || undefined }), { resetSelection: true });
};

const onFilter = (event) => {
    resetPagination();

    reload(buildParams({
        page: 1,
        global: event.filters.global?.value || undefined,
        name: event.filters.name?.value || undefined,
        email: event.filters.email?.value || undefined,
        status: event.filters.isActive?.value || undefined,
        verified: event.filters.emailVerifiedAt?.value || undefined,
    }), { resetSelection: true });
};

const onSort = (event) => {
    setSortFromEvent(event, "name");
    reload(buildParams(), { resetSelection: true });
};

const onPage = (event) => {
    setPageFromEvent(event);
    reload(buildParams(), { resetSelection: true });
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
    clearTableSelection();
    closeAllOverlays();
    reload({}, { resetSelection: true });
};

const userActionItems = (user) => [
    {
        label: "Edit",
        icon: "pi pi-pencil",
        isPrimary: true,
        command: () => openEditModal(user),
    },
    {
        label: "Delete",
        icon: "pi pi-trash",
        isDangerous: true,
        disabled: !user.canDelete,
        command: () => confirmDelete(user),
    },
];
</script>

<template>
    <Head :title="trans('navigation.users.label')" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.users.label')"
                :description="trans('navigation.users.description')"
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <BaseDataTable
                        :value="rows"
                        v-model:filters="tableFilters"
                        :rows="tableState.perPage"
                        :first="first"
                        :total-records="pagination.total"
                        :rows-per-page-options="perPageOptions"
                        :sort-field="tableState.sortField"
                        :sort-order="tableState.sortOrder"
                        :loading="busy"
                        empty-message="No users found for the current filters."
                        loading-message="Loading users..."
                        data-key="id"
                        lazy
                        filterDisplay="menu"
                        removableSort
                        responsive-layout="scroll"
                        @filter="onFilter"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                searchable
                                :search-value="tableFilters.global.value ?? ''"
                                :search-placeholder="trans('toolbar.search_placeholder')"
                                :canCreate="canManageUsers"
                                :createLabel="trans('common.create')"
                                :canBulkDelete="canManageUsers"
                                :bulkDeleteLabel="trans('toolbar.bulk.delete')"
                                :selectedCount="selectedCount"
                                :selectableCount="selectableCount"
                                :busy="busy"
                                @update:searchValue="onGlobalFilterInput"
                                @create="openCreateModal"
                                @bulk-delete="confirmBulkDelete"
                                @refresh="refresh"
                            />
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div
                                    :title="
                                        selectableCount === 0
                                            ? 'No deletable users on this page.'
                                            : ''
                                    "
                                >
                                    <Checkbox
                                        :binary="true"
                                        :modelValue="allSelected"
                                        :indeterminate="partiallySelected"
                                        :disabled="selectableCount === 0"
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

                        <Column
                            field="isActive"
                            header="Status"
                            sortable
                            :showFilterMatchModes="false"
                            :showFilterOperator="false"
                            :showAddButton="false"
                        >
                            <template #body="{ data }">
                                <Tag
                                    :value="data.isActive ? 'Active' : 'Inactive'"
                                    :severity="data.isActive ? 'success' : 'warn'"
                                />
                            </template>

                            <template #filter="{ filterModel, filterCallback }">
                                <Select
                                    v-model="filterModel.value"
                                    :options="statusOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="All"
                                    class="w-full"
                                    @change="filterCallback()"
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
                            style="width: 12rem"
                        >
                            <template #body="{ data }">
                                <RowActionMenu :items="userActionItems(data)" />
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="pagination.total"
                        :from="pagination.from"
                        :to="pagination.to"
                        :current-page="pagination.currentPage"
                        :last-page="pagination.lastPage || lastPage"
                        item-label="users"
                    />
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
