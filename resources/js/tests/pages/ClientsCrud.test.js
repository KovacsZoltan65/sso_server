import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import Create from '@/Pages/Clients/Create.vue';
import Edit from '@/Pages/Clients/Edit.vue';
import Index from '@/Pages/Clients/Index.vue';
import CreateModal from '@/Pages/Clients/CreateModal.vue';
import EditModal from '@/Pages/Clients/EditModal.vue';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getLastForm, router, setPageProps } from '@/tests/mocks/inertia';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Clients CRUD frontend', () => {
    it('supports both page create navigation and modal create from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'Portal', clientId: 'client_portal', redirectUris: ['https://portal.example.com/callback'], redirectUriCount: 1, isActive: true, scopes: ['openid'], scopesCount: 1, createdAt: '2026-03-25 10:00:00' }],
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
                filters: { global: null, name: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageClients: true,
            },
        });

        await nextTick();

        const createPageButton = wrapper.findAll('button').find((button) => button.text() === 'Create Client Page');
        await createPageButton.trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.sso-clients.create'));

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');
        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('true');
    });

    it('supports page edit and quick edit modal from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 7, name: 'Portal', clientId: 'client_portal', redirectUris: ['https://portal.example.com/callback'], redirectUriCount: 1, isActive: true, scopes: ['openid'], scopesCount: 1, createdAt: '2026-03-25 10:00:00' }],
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
                filters: { global: null, name: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageClients: true,
            },
        });

        await nextTick();

        expect(wrapper.find('[data-create-modal]').attributes('data-visible')).toBe('false');
        await wrapper.find('[data-row-action="Edit"]').trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.sso-clients.edit', 7));

        await wrapper.find('[data-row-action="Quick Edit"]').trigger('click');
        expect(wrapper.find('[data-edit-modal]').attributes('data-visible')).toBe('true');
    });

    it('submits the create page form', async () => {
        const wrapper = mount(Create, {
            props: {
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
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
        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
    });

    it('prefills and submits the edit page form without showing a secret field', async () => {
        const wrapper = mount(Edit, {
            props: {
                client: {
                    id: 4,
                    name: 'Portal',
                    clientId: 'client_portal',
                    redirectUris: ['https://portal.example.com/callback'],
                    scopes: ['openid', 'email'],
                    isActive: true,
                    tokenPolicyId: null,
                },
                scopeOptions: [{ label: 'openid', value: 'openid' }, { label: 'email', value: 'email' }],
                tokenPolicies: [],
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

        expect(form.name).toBe('Portal');
        expect(form.client_id).toBe('client_portal');
        expect(form.scopes).toEqual(['openid', 'email']);
        expect(wrapper.text()).toContain('Client ID');
        expect(wrapper.text()).not.toContain('Client Secret');

        await wrapper.find('form').trigger('submit.prevent');
        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('submits the create modal and closes on success', async () => {
        const wrapper = mount(CreateModal, {
            props: {
                visible: true,
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
            },
        });

        const form = getLastForm();
        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('created')?.[0]?.[0]).toEqual({
            message: 'SSO client created successfully.',
            type: 'create',
        });
        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([false]);
    });

    it('prefills and submits the edit modal', async () => {
        const wrapper = mount(EditModal, {
            props: {
                visible: true,
                client: {
                    id: 9,
                    name: 'Portal',
                    clientId: 'client_portal',
                    redirectUris: ['https://portal.example.com/callback'],
                    scopes: ['openid'],
                    isActive: false,
                    tokenPolicyId: null,
                },
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('Portal');
        expect(form.client_id).toBe('client_portal');
        expect(form.redirect_uris).toEqual(['https://portal.example.com/callback']);

        await wrapper.find('form').trigger('submit.prevent');
        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('allows redirect uri row add/remove and renders field errors', async () => {
        const form = {
            name: '',
            redirect_uris: ['https://portal.example.com/callback'],
            scopes: [],
            is_active: true,
            errors: {
                name: 'Name is required.',
                'redirect_uris.0': 'Redirect URI is invalid.',
            },
        };

        const wrapper = mount(ClientForm, {
            props: {
                form,
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
            },
        });

        expect(wrapper.text()).toContain('Name is required.');
        expect(wrapper.text()).toContain('Redirect URI is invalid.');

        const buttons = wrapper.findAll('button');
        await buttons[0].trigger('click');
        expect(form.redirect_uris).toHaveLength(2);

        await buttons[1].trigger('click');
        expect(form.redirect_uris).toHaveLength(1);
    });

    it('shows the one-time secret notice from flash and handles delete errors', async () => {
        setPageProps({
            flash: {
                success: 'SSO client created successfully.',
                clientSecret: {
                    clientId: 'client_portal',
                    secret: 'plain-secret-value',
                },
            },
        });

        axiosDelete.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'SSO client delete failed.',
                },
            },
        });

        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 7, name: 'Portal', clientId: 'client_portal', redirectUris: ['https://portal.example.com/callback'], redirectUriCount: 1, isActive: true, scopes: ['openid'], scopesCount: 1, createdAt: '2026-03-25 10:00:00' }],
                scopeOptions: [{ label: 'openid', value: 'openid' }],
                tokenPolicies: [],
                filters: { global: null, name: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageClients: true,
            },
        });

        await nextTick();

        expect(wrapper.text()).toContain('plain-secret-value');
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'SSO client created successfully.',
        }));

        await wrapper.find('[data-row-action="Delete"]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'SSO client delete failed.',
        }));
    });
});
