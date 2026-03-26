import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Create from '@/Pages/Scopes/Create.vue';
import Edit from '@/Pages/Scopes/Edit.vue';
import Index from '@/Pages/Scopes/Index.vue';
import ScopeForm from '@/Pages/Scopes/Partials/ScopeForm.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getLastForm, router } from '@/tests/mocks/inertia';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Scopes CRUD frontend', () => {
    const baseProps = {
        rows: [
            {
                id: 1,
                name: 'Users Read',
                code: 'users.read',
                description: 'Read the user directory.',
                isActive: true,
                createdAt: '2026-03-25 10:00:00',
                clientsCount: 0,
                canDelete: true,
                deleteBlockCode: null,
                deleteBlockReason: null,
            },
            {
                id: 2,
                name: 'Profile',
                code: 'profile',
                description: 'Used by client logins.',
                isActive: true,
                createdAt: '2026-03-25 10:00:00',
                clientsCount: 1,
                canDelete: false,
                deleteBlockCode: 'assigned_clients',
                deleteBlockReason: 'This scope is assigned to clients and cannot be deleted.',
            },
        ],
        canManageScopes: true,
        filters: { global: null, name: null, code: null, status: null },
        pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 2, from: 1, to: 2, first: 0 },
        sorting: { field: 'name', order: 1 },
    };

    it('navigates to the create page from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(router.get).toHaveBeenCalledWith(route('admin.scopes.create'));
    });

    it('navigates to edit and deletes a deletable row', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        await wrapper.find('[data-row-action="Edit"]').trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.scopes.edit', 1));

        await wrapper.find('[data-row-action="Delete"]').trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();
        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('triggers bulk delete from the shared toolbar', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        const checkboxes = wrapper.findAll('input[type="checkbox"]').filter((checkbox) => !checkbox.attributes('disabled'));
        await checkboxes[0].setValue(true);
        await nextTick();
        await wrapper.find('[data-toolbar-action="bulk-delete"]').trigger('click');

        expect(confirmRequire).toHaveBeenCalledTimes(1);
        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('uses the shared refresh action and shows in-use tag', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('In Use');

        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');
        await nextTick();

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'scopes refreshed successfully.',
        }));
    });

    it('wires the shared paginator props into the scopes table', async () => {
        const wrapper = mountPage(Index, {
            props: {
                ...baseProps,
                pagination: { currentPage: 2, lastPage: 3, perPage: 15, total: 31, from: 16, to: 30, first: 15 },
            },
        });

        await nextTick();

        const dataTable = wrapper.find('.datatable-stub');

        expect(dataTable.attributes('data-paginator')).toBe('true');
        expect(dataTable.attributes('data-rows')).toBe('15');
        expect(dataTable.attributes('data-first')).toBe('15');
        expect(dataTable.attributes('data-total-records')).toBe('31');
        expect(dataTable.attributes('data-rows-per-page-options')).toBe('5,10,15,25');
        expect(dataTable.attributes('data-always-show-paginator')).toBe('true');
        expect(dataTable.attributes('data-paginator-template')).toContain('RowsPerPageDropdown');
    });

    it('falls back to the previous page after deleting the last row on a page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                ...baseProps,
                rows: [baseProps.rows[0]],
                pagination: { currentPage: 2, lastPage: 2, perPage: 10, total: 11, from: 11, to: 11, first: 10 },
            },
        });

        await nextTick();
        await wrapper.find('[data-row-action="Delete"]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();

        expect(router.get).toHaveBeenLastCalledWith(
            route('admin.scopes.index'),
            expect.objectContaining({
                page: 1,
                perPage: 10,
            }),
            expect.any(Object),
        );
    });

    it('submits the create page form', async () => {
        const wrapper = mount(Create, {
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot /></div>' },
                    PageHeader: { template: '<div />' },
                },
            },
        });

        const form = getLastForm();

        await wrapper.find('form').trigger('submit.prevent');
        expect(form.post).toHaveBeenCalledTimes(1);
    });

    it('loads the selected scope into the edit page form and submits it', async () => {
        const wrapper = mount(Edit, {
            props: {
                scope: {
                    id: 9,
                    name: 'Clients Manage',
                    code: 'clients.manage',
                    description: 'Manage client registrations.',
                    isActive: false,
                },
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot /></div>' },
                    PageHeader: { template: '<div />' },
                },
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('Clients Manage');
        expect(form.code).toBe('clients.manage');
        expect(form.is_active).toBe(false);

        await wrapper.find('form').trigger('submit.prevent');
        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('renders scope form fields and validation errors', async () => {
        const form = {
            name: '',
            code: '',
            description: '',
            is_active: true,
            errors: {
                name: 'Name is required.',
                code: 'Code is required.',
            },
        };

        const wrapper = mount(ScopeForm, {
            props: {
                form,
            },
        });

        expect(wrapper.text()).toContain('Name is required.');
        expect(wrapper.text()).toContain('Code is required.');

        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(false);
        expect(form.is_active).toBe(false);
    });
});
