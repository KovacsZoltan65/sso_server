import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import Index from '@/Pages/ClientUserAccess/Index.vue';
import CreateDialog from '@/Pages/ClientUserAccess/components/CreateDialog.vue';
import EditDialog from '@/Pages/ClientUserAccess/components/EditDialog.vue';
import ClientUserAccessFormFields from '@/Pages/ClientUserAccess/components/ClientUserAccessFormFields.vue';
import { axiosDelete, axiosPost, axiosPut } from '@/tests/mocks/axios';
import { confirmRequire } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

const clientOptions = [
    { id: 1, name: 'Portal', clientId: 'client_portal' },
    { id: 2, name: 'Admin', clientId: 'client_admin' },
];

const userOptions = [
    { id: 11, name: 'Jane Doe', email: 'jane@example.com', isActive: true },
    { id: 12, name: 'John Roe', email: 'john@example.com', isActive: true },
];

const rows = [
    {
        id: 7,
        clientId: 1,
        clientName: 'Portal',
        clientPublicId: 'client_portal',
        userId: 11,
        userName: 'Jane Doe',
        userEmail: 'jane@example.com',
        isActive: true,
        allowedFrom: '2026-04-01T08:00:00Z',
        allowedUntil: '2026-04-01T18:00:00Z',
        notes: 'Primary rollout',
        createdAt: '2026-04-01 08:00:00',
        canDelete: true,
    },
];

describe('Client user access CRUD frontend', () => {
    it('renders the list and opens the create dialog', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows,
                clientOptions,
                userOptions,
                filters: { global: null, client_id: null, user_id: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'createdAt', order: -1 },
                canManageClientAccess: true,
            },
        });

        await nextTick();

        expect(wrapper.text()).toContain('Portal');
        expect(wrapper.text()).toContain('jane@example.com');
        expect(wrapper.text()).toContain('If a client has no active client access records, it behaves as an open client');

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');
        expect(wrapper.find('[data-dialog="Create Client Access"]').exists()).toBe(true);
    });

    it('submits the create dialog through the service layer', async () => {
        const wrapper = mount(CreateDialog, {
            props: {
                visible: true,
                clientOptions,
                userOptions,
            },
        });

        const selects = wrapper.findAll('select');
        await selects[0].setValue('1');
        await selects[1].setValue('11');
        await wrapper.find('form').trigger('submit.prevent');

        expect(axiosPost).toHaveBeenCalledWith(
            route('api.client-user-access.store'),
            expect.objectContaining({
                client_id: 1,
                user_id: 11,
                is_active: true,
            }),
        );
    });

    it('shows validation errors in the create dialog on 422 responses', async () => {
        axiosPost.mockRejectedValueOnce({
            response: {
                data: {
                    errors: {
                        user_id: ['This user already has an access record for the selected client.'],
                    },
                },
            },
        });

        const wrapper = mount(CreateDialog, {
            props: {
                visible: true,
                clientOptions,
                userOptions,
            },
        });

        const selects = wrapper.findAll('select');
        await selects[0].setValue('1');
        await selects[1].setValue('11');
        await wrapper.find('form').trigger('submit.prevent');
        await nextTick();

        expect(wrapper.text()).toContain('This user already has an access record for the selected client.');
    });

    it('prefills and submits the edit dialog through the service layer', async () => {
        const wrapper = mount(EditDialog, {
            props: {
                visible: true,
                access: rows[0],
                clientOptions,
                userOptions,
            },
        });

        await wrapper.find('textarea').setValue('Updated notes');
        await wrapper.find('form').trigger('submit.prevent');

        expect(axiosPut).toHaveBeenCalledWith(
            route('api.client-user-access.update', 7),
            expect.objectContaining({
                notes: 'Updated notes',
            }),
        );
    });

    it('confirms delete and bulk delete flows from the index', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows,
                clientOptions,
                userOptions,
                filters: { global: null, client_id: null, user_id: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'createdAt', order: -1 },
                canManageClientAccess: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-row-action="Delete"]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();

        expect(axiosDelete).toHaveBeenCalledWith(route('api.client-user-access.destroy', 7), undefined);

        await wrapper.find('input[type="checkbox"]').setValue(true);
        await wrapper.find('[data-toolbar-action="bulk-delete"]').trigger('click');
        await confirmRequire.mock.calls.at(-1)[0].accept();

        expect(axiosDelete).toHaveBeenCalledWith(
            route('api.client-user-access.bulk-destroy'),
            expect.objectContaining({
                data: { ids: [7] },
            }),
        );
    });

    it('renders field-level errors in the shared form fields component', () => {
        const wrapper = mount(ClientUserAccessFormFields, {
            props: {
                form: {
                    client_id: null,
                    user_id: null,
                    is_active: true,
                    allowed_from: '',
                    allowed_until: '',
                    notes: '',
                    errors: {
                        client_id: 'The client field is required.',
                        allowed_until: 'The allowed until field must be a date after or equal to allowed from.',
                    },
                },
                clientOptions,
                userOptions,
            },
        });

        expect(wrapper.text()).toContain('The client field is required.');
        expect(wrapper.text()).toContain('The allowed until field must be a date after or equal to allowed from.');
    });

});
