import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import CreateModal from '@/Pages/Permissions/CreateModal.vue';
import EditModal from '@/Pages/Permissions/EditModal.vue';
import Index from '@/Pages/Permissions/Index.vue';
import PermissionFormFields from '@/Pages/Permissions/Partials/PermissionFormFields.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getLastForm, router, setPageUrl } from '@/tests/mocks/inertia';
import { confirmClose, confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Permissions modal CRUD frontend', () => {
    it('opens the create modal from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'reports.view', guardName: 'web', rolesCount: 0, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');
    });

    it('opens edit and triggers delete confirmation from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reports.export', guardName: 'web', rolesCount: 2, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();

        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[0].trigger('click');
        expect(wrapper.find('[data-edit-modal]').attributes('data-visible')).toBe('true');

        await rowActionButtons[1].trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('triggers bulk delete from the shared toolbar', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reports.export', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();

        await wrapper.find('input[type="checkbox"]').setValue(true);
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
                canManagePermissions: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Frissítés',
        }));
        expect(confirmClose).toHaveBeenCalled();
    });

    it('clears selection when the shared refresh action reloads the table', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'reports.view', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();

        await wrapper.find('input[type="checkbox"]').setValue(true);
        expect(wrapper.find('[data-toolbar-action="bulk-delete"]').attributes('disabled')).toBeUndefined();

        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-toolbar-action="bulk-delete"]').attributes('disabled')).toBeDefined();
    });

    it('wires the shared paginator props into the permissions table', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'reports.view', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 2, lastPage: 3, perPage: 15, total: 31, from: 16, to: 30, first: 15 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
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
                rows: [{ id: 9, name: 'reports.export', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 2, lastPage: 2, perPage: 10, total: 11, from: 11, to: 11, first: 10 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();
        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[1].trigger('click');
        await confirmRequire.mock.calls[0][0].accept();

        expect(router.get).toHaveBeenLastCalledWith(
            JSON.stringify({ name: 'admin.permissions.index', params: undefined }),
            expect.objectContaining({
                page: 1,
                perPage: 10,
            }),
            expect.any(Object),
        );
    });

    it('closes modal state when the page url changes', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'reports.view', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();
        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');

        setPageUrl('/admin/scopes');
        await nextTick();

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');
    });

    it('submits the create modal and closes on success', async () => {
        const wrapper = mount(CreateModal, {
            props: {
                visible: true,
            },
            global: {
                stubs: {
                    PermissionFormFields: true,
                },
            },
        });

        const form = getLastForm();

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'Permission created successfully.',
            type: 'create',
        });
        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([false]);
    });

    it('syncs the selected permission into the edit modal form', async () => {
        const wrapper = mount(EditModal, {
            props: {
                visible: true,
                permission: {
                    id: 9,
                    name: 'reports.download',
                },
            },
            global: {
                stubs: {
                    PermissionFormFields: true,
                },
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('reports.download');

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'Permission updated successfully.',
            type: 'edit',
        });
    });

    it('renders permission field validation errors', () => {
        const wrapper = mount(PermissionFormFields, {
            props: {
                form: {
                    name: '',
                    errors: {
                        name: 'Name is required.',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Name');
        expect(wrapper.text()).toContain('Name is required.');
    });

    it('shows an error toast when delete fails', async () => {
        axiosDelete.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Permission delete failed.',
                },
            },
        });

        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 6, name: 'reports.view', guardName: 'web', rolesCount: 0, usersCount: 0, canDelete: true, createdAt: '2026-03-25 10:00:00' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManagePermissions: true,
            },
        });

        await nextTick();

        const rowActionButtons = wrapper.findAll('[data-row-action]');
        await rowActionButtons[1].trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Permission delete failed.',
        }));
    });
});
