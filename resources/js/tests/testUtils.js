import { mount } from '@vue/test-utils';

export const mountPage = (component, options = {}) => mount(component, {
    global: {
        stubs: {
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
            ...options.global?.stubs,
        },
        ...options.global,
    },
    ...options,
});
