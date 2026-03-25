import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import CreateModal from '@/Pages/Roles/CreateModal.vue';
import EditModal from '@/Pages/Roles/EditModal.vue';
import Index from '@/Pages/Roles/Index.vue';
import RoleFormFields from '@/Pages/Roles/Partials/RoleFormFields.vue';
import { getLastForm, router, setPageProps } from '@/tests/mocks/inertia';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Roles modal CRUD frontend', () => {
    it('opens the create modal from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'auditor', guardName: 'web', permissions: [], usersCount: 0, createdAt: '2026-03-25 10:00:00' }],
                permissionOptions: [{ label: 'reports.view', value: 'reports.view' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');

        await wrapper.find('button').trigger('click');

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');
    });

    it('opens edit and triggers delete confirmation from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 5, name: 'reviewer', guardName: 'web', permissions: ['reports.view'], usersCount: 2, createdAt: '2026-03-25 10:00:00' }],
                permissionOptions: [{ label: 'reports.view', value: 'reports.view' }],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: true,
            },
        });

        await nextTick();

        const buttons = wrapper.findAll('button');

        await buttons[1].trigger('click');
        expect(wrapper.find('[data-edit-modal]').attributes('data-visible')).toBe('true');

        await buttons[2].trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);

        confirmRequire.mock.calls[0][0].accept();
        expect(router.delete).toHaveBeenCalledTimes(1);
    });

    it('submits the create modal and closes on success', async () => {
        const wrapper = mount(CreateModal, {
            props: {
                visible: true,
                permissionOptions: [{ label: 'reports.view', value: 'reports.view' }],
            },
            global: {
                stubs: {
                    RoleFormFields: true,
                },
            },
        });

        const form = getLastForm();

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'Role created successfully.',
            type: 'create',
        });
        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([false]);
    });

    it('syncs the selected role into the edit modal form', async () => {
        const wrapper = mount(EditModal, {
            props: {
                visible: true,
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
                    RoleFormFields: true,
                },
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('manager');
        expect(form.permissions).toEqual(['reports.view', 'reports.export']);

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'Role updated successfully.',
            type: 'edit',
        });
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

        await wrapper.find('select').setValue(['reports.view']);
        expect(form.permissions).toEqual(['reports.view']);
    });

    it('shows an error toast when the page flash contains an error', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [],
                permissionOptions: [],
                filters: { global: null, name: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 0, from: 0, to: 0, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageRoles: false,
            },
        });

        setPageProps({
            flash: {
                error: 'Role delete failed.',
            },
        });

        await wrapper.vm.$nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Role delete failed.',
        }));
    });
});
