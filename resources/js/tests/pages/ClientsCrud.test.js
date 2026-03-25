import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import Create from '@/Pages/Clients/Create.vue';
import Edit from '@/Pages/Clients/Edit.vue';
import Index from '@/Pages/Clients/Index.vue';
import ClientForm from '@/Pages/Clients/Partials/ClientForm.vue';
import { axiosDelete } from '@/tests/mocks/axios';
import { getLastForm, router, setPageProps } from '@/tests/mocks/inertia';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

const scopeOptions = [
    {
        label: 'openid',
        value: 'openid',
        groupKey: 'identity',
        groupLabel: 'Identity',
        action: 'openid',
        itemLabel: 'OpenID',
        helper: 'Authenticate the subject and issue an ID token.',
    },
    {
        label: 'email',
        value: 'email',
        groupKey: 'identity',
        groupLabel: 'Identity',
        action: 'email',
        itemLabel: 'Email',
        helper: 'Access verified email claims for the subject.',
    },
    {
        label: 'offline_access',
        value: 'offline_access',
        groupKey: 'session',
        groupLabel: 'Session',
        action: 'offlineAccess',
        itemLabel: 'Offline Access',
        helper: 'Allow refresh-token based session continuation.',
    },
];

describe('Clients CRUD frontend', () => {
    it('navigates to the create page from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 1, name: 'Portal', clientId: 'client_portal', redirectUris: ['https://portal.example.com/callback'], redirectUriCount: 1, isActive: true, scopes: ['openid'], scopesCount: 1, createdAt: '2026-03-25 10:00:00' }],
                scopeOptions,
                tokenPolicies: [],
                filters: { global: null, name: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageClients: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-toolbar-action="create"]').trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.sso-clients.create'));
    });

    it('navigates to edit from the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{ id: 7, name: 'Portal', clientId: 'client_portal', redirectUris: ['https://portal.example.com/callback'], redirectUriCount: 1, isActive: true, scopes: ['openid'], scopesCount: 1, createdAt: '2026-03-25 10:00:00' }],
                scopeOptions,
                tokenPolicies: [],
                filters: { global: null, name: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageClients: true,
            },
        });

        await nextTick();

        await wrapper.find('[data-row-action="Edit"]').trigger('click');
        expect(router.get).toHaveBeenCalledWith(route('admin.sso-clients.edit', 7));
    });

    it('submits the create page form', async () => {
        const wrapper = mount(Create, {
            props: {
                scopeOptions,
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
        expect(wrapper.text()).toContain('Identity');
        expect(wrapper.text()).toContain('OpenID');
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
                scopeOptions,
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
                scopeOptions,
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

    it('renders grouped scopes, supports search, and updates checkbox selection', async () => {
        const form = {
            name: 'Portal',
            redirect_uris: ['https://portal.example.com/callback'],
            scopes: ['openid'],
            is_active: true,
            errors: {},
        };

        const wrapper = mount(ClientForm, {
            props: {
                form,
                scopeOptions,
                tokenPolicies: [],
            },
        });

        expect(wrapper.text()).toContain('Identity');
        expect(wrapper.text()).toContain('Session');
        expect(wrapper.text()).toContain('OpenID');
        expect(wrapper.text()).toContain('Offline Access');

        const searchInput = wrapper.find('input[type="search"]');
        await searchInput.setValue('offline');

        expect(wrapper.text()).not.toContain('OpenID');
        expect(wrapper.text()).toContain('Offline Access');

        await searchInput.setValue('');

        const selectAllButtons = wrapper.findAll('button').filter((button) => button.text() === 'Select all');
        await selectAllButtons[0].trigger('click');

        expect(form.scopes).toEqual(['openid', 'email']);
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
                scopeOptions,
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
