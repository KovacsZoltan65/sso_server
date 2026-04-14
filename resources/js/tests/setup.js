import { config } from '@vue/test-utils';
import { defineComponent, h, inject, provide, ref } from 'vue';
import { afterEach, beforeEach, vi } from 'vitest';
import en from '../../../lang/en.json';
import hu from '../../../lang/hu.json';
import { axiosDelete, axiosPost, axiosPut, resetAxiosMocks } from './mocks/axios';
import { createMockForm, getPage, resetInertiaMocks, router } from './mocks/inertia';
import { confirmClose, confirmRequire, resetPrimeVueMocks, toastAdd } from './mocks/primevue';

const dataTableColumnsKey = Symbol('dataTableColumns');
const translations = { en, hu };

const translate = (key, replacements = {}) => {
    const locale = getPage().props.locale?.current ?? 'hu';
    const fallback = getPage().props.locale?.fallback ?? 'en';
    const message = translations[locale]?.[key] ?? translations[fallback]?.[key] ?? key;

    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        message,
    );
};

const makeFieldComponent = (tag = 'input') => defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: [String, Number, Array, Object, Boolean, null],
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        type: {
            type: String,
            default: 'text',
        },
    },
    emits: ['update:modelValue', 'input', 'change'],
    setup(props, { attrs, emit }) {
        return () => h(tag, {
            ...attrs,
            disabled: props.disabled,
            type: props.type,
            value: Array.isArray(props.modelValue) ? undefined : props.modelValue,
            onInput: (event) => {
                emit('update:modelValue', event.target.value);
                emit('input', event);
            },
            onChange: (event) => emit('change', event),
        });
    },
});

const ButtonStub = defineComponent({
    inheritAttrs: false,
    props: {
        label: {
            type: String,
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['click'],
    setup(props, { attrs, emit, slots }) {
        return () => h('button', {
            ...attrs,
            disabled: props.disabled,
            'data-loading': props.loading ? 'true' : 'false',
            onClick: (event) => emit('click', event),
        }, slots.default ? slots.default() : props.label);
    },
});

const DialogStub = defineComponent({
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
        header: {
            type: String,
            default: '',
        },
    },
    setup(props, { slots }) {
        return () => props.visible
            ? h('section', { 'data-dialog': props.header || 'dialog' }, [
                props.header ? h('h2', props.header) : null,
                slots.default?.(),
            ])
            : null;
    },
});

const CardStub = defineComponent({
    setup(_props, { slots }) {
        return () => h('div', { class: 'card-stub' }, slots.content ? slots.content() : slots.default?.());
    },
});

const ColumnStub = defineComponent({
    props: {
        field: {
            type: String,
            default: '',
        },
        header: {
            type: String,
            default: '',
        },
    },
    setup(props, { slots }) {
        const registerColumn = inject(dataTableColumnsKey, null);

        registerColumn?.({
            field: props.field,
            header: props.header,
            slots,
        });

        return () => null;
    },
});

const DataTableStub = defineComponent({
    props: {
        filters: {
            type: Object,
            default: () => ({}),
        },
        value: {
            type: Array,
            default: () => [],
        },
        paginator: {
            type: Boolean,
            default: false,
        },
        rows: {
            type: Number,
            default: 0,
        },
        first: {
            type: Number,
            default: 0,
        },
        totalRecords: {
            type: Number,
            default: 0,
        },
        rowsPerPageOptions: {
            type: Array,
            default: () => [],
        },
        alwaysShowPaginator: {
            type: Boolean,
            default: false,
        },
        paginatorTemplate: {
            type: String,
            default: '',
        },
        currentPageReportTemplate: {
            type: String,
            default: '',
        },
        scrollHeight: {
            type: String,
            default: '',
        },
    },
    emits: ['filter', 'update:filters'],
    setup(props, { slots, emit }) {
        const columns = ref([]);
        const localFilters = ref(props.filters);

        provide(dataTableColumnsKey, (column) => {
            columns.value.push(column);
        });

        return () => h('div', {
            class: 'datatable-stub',
            'data-paginator': String(props.paginator),
            'data-rows': String(props.rows),
            'data-first': String(props.first),
            'data-total-records': String(props.totalRecords),
            'data-rows-per-page-options': props.rowsPerPageOptions.join(','),
            'data-always-show-paginator': String(props.alwaysShowPaginator),
            'data-paginator-template': props.paginatorTemplate,
            'data-current-page-report-template': props.currentPageReportTemplate,
        }, [
            slots.header?.(),
            h('div', { class: 'datatable-filters' }, columns.value.map((column, columnIndex) => {
                if (!column.slots.filter) {
                    return null;
                }

                const filterKey = column.field || String(columnIndex);
                if (!localFilters.value[filterKey]) {
                    localFilters.value[filterKey] = { value: null };
                }

                return h('div', {
                    class: 'datatable-filter',
                    'data-filter-column-index': columnIndex,
                    'data-filter-header': column.header || column.field,
                }, column.slots.filter({
                    filterModel: localFilters.value[filterKey],
                    filterCallback: () => {
                        emit('update:filters', localFilters.value);
                        emit('filter', { filters: localFilters.value });
                    },
                }));
            })),
            slots.default?.(),
            props.value.length === 0
                ? slots.empty?.()
                : props.value.map((row, rowIndex) => h('div', {
                    class: 'datatable-row',
                    'data-row-index': rowIndex,
                }, columns.value.map((column, columnIndex) => h('div', {
                    class: 'datatable-cell',
                    'data-column-index': columnIndex,
                    'data-column-header': column.header || column.field,
                }, column.slots.body ? column.slots.body({ data: row }) : row[column.field])))),
        ]);
    },
});

const PaginatorStub = defineComponent({
    props: {
        rows: {
            type: Number,
            default: 0,
        },
        first: {
            type: Number,
            default: 0,
        },
        totalRecords: {
            type: Number,
            default: 0,
        },
        rowsPerPageOptions: {
            type: Array,
            default: () => [],
        },
        alwaysShow: {
            type: Boolean,
            default: false,
        },
        template: {
            type: String,
            default: '',
        },
        currentPageReportTemplate: {
            type: String,
            default: '',
        },
    },
    emits: ['page'],
    setup(props, { slots }) {
        return () => h('div', {
            class: 'paginator-stub',
            'data-rows': String(props.rows),
            'data-first': String(props.first),
            'data-total-records': String(props.totalRecords),
            'data-rows-per-page-options': props.rowsPerPageOptions.join(','),
            'data-always-show': String(props.alwaysShow),
            'data-template': props.template,
            'data-current-page-report-template': props.currentPageReportTemplate,
        }, slots.default?.());
    },
});

const MultiSelectStub = defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
        options: {
            type: Array,
            default: () => [],
        },
        optionLabel: {
            type: String,
            default: 'label',
        },
        optionValue: {
            type: String,
            default: 'value',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('select', {
            ...attrs,
            multiple: true,
            disabled: props.disabled,
            onChange: (event) => {
                const selected = Array.from(event.target.selectedOptions).map((option) => option.value);
                emit('update:modelValue', selected);
            },
        }, props.options.map((option) => h('option', {
            value: option[props.optionValue],
            selected: props.modelValue.includes(option[props.optionValue]),
        }, option[props.optionLabel])));
    },
});

const SelectStub = defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: [String, Number, null],
            default: null,
        },
        options: {
            type: Array,
            default: () => [],
        },
        optionLabel: {
            type: String,
            default: 'label',
        },
        optionValue: {
            type: String,
            default: 'value',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        placeholder: {
            type: String,
            default: '',
        },
    },
    emits: ['update:modelValue', 'change'],
    setup(props, { attrs, emit }) {
        return () => h('select', {
            ...attrs,
            disabled: props.disabled,
            value: props.modelValue ?? '',
            onChange: (event) => {
                emit('update:modelValue', event.target.value);
                emit('change', event);
            },
        }, [
            props.placeholder ? h('option', { value: '' }, props.placeholder) : null,
            ...props.options.map((option) => h('option', {
                value: option[props.optionValue],
                selected: String(option[props.optionValue]) === String(props.modelValue ?? ''),
            }, option[props.optionLabel])),
        ]);
    },
});

const CheckboxStub = defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('input', {
            ...attrs,
            type: 'checkbox',
            checked: props.modelValue,
            disabled: props.disabled,
            onChange: (event) => emit('update:modelValue', event.target.checked),
        });
    },
});

const TagStub = defineComponent({
    props: {
        value: {
            type: String,
            default: '',
        },
    },
    setup(props) {
        return () => h('span', { class: 'tag-stub' }, props.value);
    },
});

const passthroughStub = defineComponent({
    setup(_props, { slots }) {
        return () => h('div', slots.default?.());
    },
});

const MenuStub = defineComponent({
    inheritAttrs: false,
    props: {
        model: {
            type: Array,
            default: () => [],
        },
        popup: {
            type: Boolean,
            default: false,
        },
        appendTo: {
            type: String,
            default: null,
        },
        pt: {
            type: Object,
            default: () => ({}),
        },
    },
    setup(props, { attrs, expose }) {
        const open = ref(false);

        expose({
            toggle: () => {
                open.value = !open.value;
            },
            hide: () => {
                open.value = false;
            },
        });

        return () => open.value
            ? h('div', {
                ...attrs,
                'data-menu-popup': props.popup ? 'true' : 'false',
                'data-menu-append-to': props.appendTo ?? '',
            }, props.model.map((item) => h('button', {
                type: 'button',
                disabled: Boolean(item.disabled),
                onClick: () => item.command?.({ item }),
            }, item.label)))
            : null;
    },
});

vi.mock('@inertiajs/vue3', async () => {
    const vue = await import('vue');

    return {
        router,
        usePage: () => getPage(),
        useForm: (initialValues) => createMockForm(initialValues),
        Head: vue.defineComponent({
            props: {
                title: {
                    type: String,
                    default: '',
                },
            },
            setup(_props, { slots }) {
                return () => vue.h('div', { 'data-head': 'true' }, slots.default?.());
            },
        }),
    };
});

vi.mock('laravel-vue-i18n', () => ({
    trans: translate,
    wTrans: (key, replacements = {}) => ({ value: translate(key, replacements) }),
    i18nVue: {
        install(app) {
            app.config.globalProperties.$t = translate;
            app.config.globalProperties.trans = translate;
        },
    },
}));

vi.mock('axios', () => ({
    default: {
        delete: axiosDelete,
        post: axiosPost,
        put: axiosPut,
    },
}));

vi.mock('primevue/usetoast', () => ({
    useToast: () => ({
        add: toastAdd,
    }),
}));

vi.mock('primevue/useconfirm', () => ({
    useConfirm: () => ({
        require: confirmRequire,
        close: confirmClose,
    }),
}));

vi.mock('primevue/button', () => ({ default: ButtonStub }));
vi.mock('primevue/dialog', () => ({ default: DialogStub }));
vi.mock('primevue/card', () => ({ default: CardStub }));
vi.mock('primevue/checkbox', () => ({ default: CheckboxStub }));
vi.mock('primevue/column', () => ({ default: ColumnStub }));
vi.mock('primevue/datatable', () => ({ default: DataTableStub }));
vi.mock('primevue/iconfield', () => ({ default: passthroughStub }));
vi.mock('primevue/inputicon', () => ({ default: passthroughStub }));
vi.mock('primevue/inputnumber', () => ({ default: makeFieldComponent('input') }));
vi.mock('primevue/inputtext', () => ({ default: makeFieldComponent('input') }));
vi.mock('primevue/multiselect', () => ({ default: MultiSelectStub }));
vi.mock('primevue/menu', () => ({ default: MenuStub }));
vi.mock('primevue/password', () => ({ default: makeFieldComponent('input') }));
vi.mock('primevue/paginator', () => ({ default: PaginatorStub }));
vi.mock('primevue/select', () => ({ default: SelectStub }));
vi.mock('primevue/tag', () => ({ default: TagStub }));
vi.mock('primevue/textarea', () => ({ default: makeFieldComponent('textarea') }));
vi.mock('primevue/toast', () => ({ default: passthroughStub }));
vi.mock('primevue/confirmdialog', () => ({ default: passthroughStub }));
vi.mock('@primevue/core/api', () => ({
    FilterMatchMode: {
        CONTAINS: 'contains',
        EQUALS: 'equals',
    },
}));

global.route = vi.fn((name, params) => JSON.stringify({ name, params }));
global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

config.global.stubs = {
    teleport: true,
};

beforeEach(() => {
    resetAxiosMocks();
    resetInertiaMocks();
    resetPrimeVueMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});
