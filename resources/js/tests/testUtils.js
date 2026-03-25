import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

const AdminTableToolbarStub = defineComponent({
    props: ['canCreate', 'canBulkDelete', 'busy', 'selectedCount', 'createLabel', 'bulkDeleteLabel'],
    emits: ['create', 'bulk-delete', 'refresh'],
    setup(props, { emit, slots }) {
        return () => h('div', { 'data-admin-toolbar': 'true' }, [
            slots.search?.(),
            h('button', {
                'data-toolbar-action': 'refresh',
                disabled: props.busy,
                onClick: () => emit('refresh'),
            }, 'Refresh'),
            props.canBulkDelete
                ? h('button', {
                    'data-toolbar-action': 'bulk-delete',
                    disabled: props.busy || props.selectedCount === 0,
                    onClick: () => emit('bulk-delete'),
                }, props.bulkDeleteLabel || 'Delete Selected')
                : null,
            props.canCreate
                ? h('button', {
                    'data-toolbar-action': 'create',
                    disabled: props.busy,
                    onClick: () => emit('create'),
                }, props.createLabel || 'Create')
                : null,
        ]);
    },
});

const RowActionMenuStub = defineComponent({
    props: ['items'],
    setup(props) {
        return () => h('div', { 'data-row-action-menu': 'true' }, (props.items ?? []).map((item) => h('button', {
            'data-row-action': item.label,
            disabled: item.disabled,
            onClick: () => item.command?.(),
        }, item.label)));
    },
});

export const mountPage = (component, options = {}) => mount(component, {
    global: {
        stubs: {
            AdminTableToolbar: AdminTableToolbarStub,
            AuthenticatedLayout: {
                template: '<div><slot /></div>',
            },
            PageHeader: {
                props: ['title', 'description'],
                template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>',
            },
            CreateModal: {
                props: ['visible', 'permissionOptions', 'roleOptions'],
                template: '<div data-create-modal :data-visible="String(visible)"></div>',
            },
            EditModal: {
                props: ['visible', 'permission', 'role', 'permissionOptions', 'roleOptions'],
                template: '<div data-edit-modal :data-visible="String(visible)"></div>',
            },
            RowActionMenu: RowActionMenuStub,
            ...options.global?.stubs,
        },
        ...options.global,
    },
    ...options,
});
