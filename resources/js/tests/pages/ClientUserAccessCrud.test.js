import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import Create from '@/Pages/ClientUserAccess/Create.vue';
import Edit from '@/Pages/ClientUserAccess/Edit.vue';
import Index from '@/Pages/ClientUserAccess/Index.vue';
import ClientUserAccessFormFields from '@/Pages/ClientUserAccess/components/ClientUserAccessFormFields.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getForms, getLastForm, router } from '@/tests/mocks/inertia';
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
    it('renders the list and navigates to the create page', async () => {
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
        expect(router.get).toHaveBeenCalledWith(route('admin.client-user-access.create'));
    });

    it('navigates to the edit page from the index', async () => {
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

        await wrapper.find('[data-row-action="Edit"]').trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.client-user-access.edit', 7));
    });

    it('submits the create page form through inertia useForm', async () => {
        const wrapper = mount(Create, {
            props: {
                clientOptions,
                userOptions,
            },
            global: {
                stubs: {
                    AuthenticatedLayout: {
                        template: '<div><slot /></div>',
                    },
                    PageHeader: {
                        template: '<div />',
                    },
                },
            },
        });

        const form = getLastForm();

        expect(wrapper.text()).toContain('Restriction behavior');
        form.client_id = 1;
        form.user_id = 11;
        form.notes = 'Rollout access';

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledWith(route('admin.client-user-access.store'), {
            preserveScroll: true,
        });
    });

    it('prefills and submits the edit page form through inertia useForm', async () => {
        const wrapper = mount(Edit, {
            props: {
                access: rows[0],
                clientOptions,
                userOptions,
            },
            global: {
                stubs: {
                    AuthenticatedLayout: {
                        template: '<div><slot /></div>',
                    },
                    PageHeader: {
                        template: '<div />',
                    },
                },
            },
        });

        const [form] = getForms();

        expect(form.client_id).toBe(1);
        expect(form.user_id).toBe(11);
        expect(form.allowed_from).toBe('2026-04-01T08:00');
        expect(form.allowed_until).toBe('2026-04-01T18:00');
        expect(form.notes).toBe('Primary rollout');

        form.notes = 'Updated notes';
        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledWith(route('admin.client-user-access.update', 7), {
            preserveScroll: true,
        });
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

        expect(wrapper.text()).toContain('Portal (client_portal)');
        expect(wrapper.text()).toContain('Jane Doe (jane@example.com)');
        expect(wrapper.text()).toContain('The client field is required.');
        expect(wrapper.text()).toContain('The allowed until field must be a date after or equal to allowed from.');
    });
});
