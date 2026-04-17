import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import GroupedCheckboxSelector from '@/Components/Admin/GroupedCheckboxSelector.vue';
import Create from '@/Pages/Roles/Create.vue';
import Edit from '@/Pages/Roles/Edit.vue';
import Index from '@/Pages/Roles/Index.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getLastForm, router } from '@/tests/mocks/inertia';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Roles page CRUD frontend', () => {
    it('navigates to the create page from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'auditor', guardName: 'web', permissions: [], usersCount: 0, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(router.get).toHaveBeenCalledWith(route('admin.roles.create'));
    });

    it('navigates to edit and still triggers delete confirmation from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reviewer', guardName: 'web', permissions: ['reports.view'], usersCount: 2, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[0].trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.roles.edit', 5));

        await rowActionButtons[1].trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('triggers bulk delete from the shared toolbar', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reviewer', guardName: 'web', permissions: ['reports.view'], usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        const checkboxes = wrapper.findAll('input[type="checkbox"]').filter((checkbox) => !checkbox.attributes('disabled'));
        if (checkboxes.length === 0) {
            expect(wrapper.find('[data-toolbar-action="bulk-delete"]').attributes('disabled')).toBeDefined();
            return;
        }

        await checkboxes[0].setValue(true);
        await wrapper.find('[data-toolbar-action="bulk-delete"]').trigger('click');

        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('uses the shared refresh action to reload the table', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 0, from: 0, to: 0, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Frissítés',
        }));
    });

    it('falls back to the previous page after deleting the last row on a page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reviewer', guardName: 'web', permissions: ['reports.view'], usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 2, lastPage: 2, perPage: 10, total: 11, from: 11, to: 11, first: 10 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();
        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[1].trigger('click');
        await confirmRequire.mock.calls[0][0].accept();

        expect(router.get).toHaveBeenLastCalledWith(
            route('admin.roles.index'),
            expect.objectContaining({
                page: 1,
                perPage: 10,
            }),
            expect.any(Object),
        );
    });

    it('submits the create page form', async () => {
        const wrapper = mount(Create, {
            props: {
                guardName: 'web',
                permissionOptions: [{ label: 'reports.view', value: 'reports.view' }],
            },
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

    it('loads the selected role into the edit page form and submits it', async () => {
        const wrapper = mount(Edit, {
            props: {
                role: {
                    id: 9,
                    name: 'manager',
                    permissions: ['reports.view', 'reports.export'],
                },
                permissionOptions: [
                    { label: 'reports.view', value: 'reports.view' },
                    { label: 'reports.export', value: 'reports.export' },
                ],
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot /></div>' },
                    PageHeader: { template: '<div />' },
                    RoleFormFields: true,
                },
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('manager');
        expect(form.permissions).toEqual(['reports.view', 'reports.export']);

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('renders role field validation errors and permission selector', async () => {
        const form = {
            name: '',
            permissions: [],
            errors: {
                name: 'Role name is required.',
                'permissions.0': 'Invalid permission.',
            },
        };

        const wrapper = mount(RoleFormFields, {
            props: {
                form,
                permissionOptions: [{ label: 'reports.view', value: 'reports.view' }],
            },
        });

        expect(wrapper.text()).toContain('Role name is required.');
        expect(wrapper.text()).toContain('Invalid permission.');
        expect(wrapper.text()).toContain('Permissions');
    });

    it('groups permissions by resource, supports search, and updates checkbox selection', async () => {
        const wrapper = mount(GroupedCheckboxSelector, {
            props: {
                modelValue: ['users.view'],
                options: [
                    { label: 'users.viewAny', value: 'users.viewAny' },
                    { label: 'users.view', value: 'users.view' },
                    { label: 'users.deleteAny', value: 'users.deleteAny' },
                    { label: 'roles.assignPermission', value: 'roles.assignPermission' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Users');
        expect(wrapper.text()).toContain('Roles');
        expect(wrapper.text()).toContain('View Any');
        expect(wrapper.text()).toContain('Assign Permission');

        const searchInput = wrapper.find('input[type="search"]');
        await searchInput.setValue('assign');

        expect(wrapper.text()).not.toContain('View Any');
        expect(wrapper.text()).toContain('Assign Permission');

        await searchInput.setValue('');

        const selectAllButtons = wrapper.findAll('button').filter((button) => /Select all|Összes kijelölése/.test(button.text()));
        await selectAllButtons.at(-1).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual([
            'users.view',
            'users.viewAny',
            'users.deleteAny',
        ]);
    });

    it('shows an error toast when delete fails', async () => {
        axiosDelete.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Role delete failed.',
                },
            },
        });

        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 9, name: 'reviewer', guardName: 'web', permissions: [], usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[1].trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Role delete failed.',
        }));
    });
});
