import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Index from '@/Pages/Admin/Users/Index.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { confirmClose, confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';
import { router, setPageUrl } from '@/tests/mocks/inertia';

describe('Admin Users index', () => {
    const baseProps = {
        rows: [
            {
                id: 1,
                name: 'Regular User',
                email: 'regular@example.com',
                roles: ['viewer'],
                emailVerifiedAt: null,
                createdAt: '2026-03-25 10:00:00',
                canDelete: true,
                deleteBlockCode: null,
                deleteBlockReason: null,
            },
            {
                id: 2,
                name: 'SSO Admin',
                email: 'admin@sso.test',
                roles: ['admin'],
                emailVerifiedAt: null,
                createdAt: '2026-03-25 10:00:00',
                canDelete: false,
                deleteBlockCode: 'protected_user',
                deleteBlockReason: 'This protected system user cannot be deleted.',
            },
        ],
        roleOptions: [{ label: 'Admin', value: 'admin' }],
        canManageUsers: true,
        filters: { global: null, name: null, email: null, verified: null },
        pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 2, from: 1, to: 2, first: 0 },
        sorting: { field: 'name', order: 1 },
    };

    it('opens the create modal from the shared toolbar', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');
    });

    it('opens edit and deletes a deletable row through the row action menu', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        await wrapper.find('[data-row-action="Edit"]').trigger('click');
        expect(wrapper.find('[data-edit-modal]').attributes('data-visible')).toBe('true');

        await wrapper.find('[data-row-action="Delete"]').trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledTimes(1);
    });

    it('triggers bulk delete from the toolbar after selecting rows', async () => {
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

    it('uses the shared refresh action and shows the protected tag', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('Protected');

        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');
        await nextTick();

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'users refreshed successfully.',
        }));
        expect(confirmClose).toHaveBeenCalled();
    });

    it('closes open modal state on page url change', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-toolbar-action="create"]').trigger('click');

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');

        setPageUrl('/admin/permissions');
        await nextTick();

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');
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
            route('admin.users.index'),
            expect.objectContaining({
                page: 1,
                perPage: 10,
            }),
            expect.any(Object),
        );
    });
});
