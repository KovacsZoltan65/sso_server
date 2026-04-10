import { computed, reactive, ref } from 'vue';

const cloneValue = (value) => JSON.parse(JSON.stringify(value ?? {}));

export function useAdminTableState(options = {}) {
    const {
        initialPage = 1,
        initialPerPage = 10,
        initialSortField = null,
        initialSortOrder = -1,
        initialTotalRecords = 0,
        initialFilters = {},
        paramNames = {
            page: 'page',
            perPage: 'perPage',
            sortField: 'sortField',
            sortOrder: 'sortOrder',
        },
        serializeSortOrder = (value) => value,
    } = options;

    const state = reactive({
        page: initialPage,
        perPage: initialPerPage,
        sortField: initialSortField,
        sortOrder: initialSortOrder === 1 ? 1 : -1,
        totalRecords: initialTotalRecords,
    });

    const filters = reactive(cloneValue(initialFilters));
    const selectedRows = ref([]);

    const first = computed(() => Math.max(0, (state.page - 1) * state.perPage));
    const lastPage = computed(() => {
        if (state.totalRecords <= 0 || state.perPage <= 0) {
            return 1;
        }

        return Math.max(1, Math.ceil(state.totalRecords / state.perPage));
    });

    const sortDirection = computed(() => (state.sortOrder === 1 ? 'asc' : 'desc'));

    const resetPagination = () => {
        state.page = 1;
    };

    const clearSelection = () => {
        selectedRows.value = [];
    };

    const setSelectedRows = (value) => {
        selectedRows.value = Array.isArray(value) ? value : [];
    };

    const setFilters = (value = {}) => {
        Object.keys(filters).forEach((key) => {
            delete filters[key];
        });

        Object.assign(filters, cloneValue(value));
    };

    const setPageFromEvent = (event = {}) => {
        state.page = (event.page ?? 0) + 1;
        state.perPage = event.rows ?? state.perPage;
    };

    const setSortFromEvent = (event = {}, fallbackField = state.sortField) => {
        state.sortField = event.sortField ?? fallbackField;
        state.sortOrder = event.sortOrder === 1 ? 1 : -1;
        resetPagination();
    };

    const applyMeta = (meta = {}) => {
        const nextPage = Number(meta.current_page ?? meta.currentPage ?? state.page);
        const nextPerPage = Number(meta.per_page ?? meta.perPage ?? state.perPage);
        const nextTotal = Number(meta.total ?? state.totalRecords);

        state.page = Number.isFinite(nextPage) && nextPage > 0 ? nextPage : state.page;
        state.perPage = Number.isFinite(nextPerPage) && nextPerPage > 0 ? nextPerPage : state.perPage;
        state.totalRecords = Number.isFinite(nextTotal) && nextTotal >= 0 ? nextTotal : 0;
    };

    const buildFetchParams = ({ filters: filterValues = {}, extra = {} } = {}) => ({
        [paramNames.page]: state.page,
        [paramNames.perPage]: state.perPage,
        [paramNames.sortField]: state.sortField,
        [paramNames.sortOrder]: serializeSortOrder(state.sortOrder),
        ...filterValues,
        ...extra,
    });

    return {
        state,
        filters,
        selectedRows,
        first,
        lastPage,
        sortDirection,
        resetPagination,
        clearSelection,
        setSelectedRows,
        setFilters,
        setPageFromEvent,
        setSortFromEvent,
        applyMeta,
        buildFetchParams,
    };
}
