import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import Create from '@/Pages/TokenPolicies/Create.vue';
import Edit from '@/Pages/TokenPolicies/Edit.vue';
import Index from '@/Pages/TokenPolicies/Index.vue';
import TokenPolicyForm from '@/Pages/TokenPolicies/Partials/TokenPolicyForm.vue';
import { getLastForm, router } from '@/tests/mocks/inertia';
import { confirmRequire } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Token Policies CRUD frontend', () => {
    it('navigates to the create page from the index', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [],
                filters: { global: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 0, from: 0, to: 0, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageTokenPolicies: true,
            },
        });

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Token Policy');
        await createButton.trigger('click');

        expect(router.get).toHaveBeenCalledWith(
            JSON.stringify({ name: 'admin.token-policies.create', params: undefined }),
        );
    });

    it('renders token policy details on the index page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{
                    id: 9,
                    name: 'Default Web Policy',
                    code: 'default.web',
                    description: 'Balanced default settings.',
                    accessTokenTtlMinutes: 60,
                    refreshTokenTtlMinutes: 43200,
                    refreshTokenRotationEnabled: true,
                    pkceRequired: false,
                    reuseRefreshTokenForbidden: true,
                    isDefault: false,
                    isActive: true,
                    createdAt: '2026-03-25 17:00:00',
                    clientsCount: 0,
                    canDelete: true,
                    deleteBlockCode: null,
                    deleteBlockReason: null,
                }],
                filters: { global: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 1, from: 1, to: 1, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageTokenPolicies: true,
            },
        });

        await nextTick();
        expect(wrapper.text()).toContain('Default Web Policy');
        expect(wrapper.text()).toContain('default.web');
        expect(wrapper.text()).toContain('Default');
        expect(wrapper.text()).toContain('Active');
    });

    it('bulk deletes selected token policies', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [
                    {
                        id: 1,
                        name: 'Default Web Policy',
                        code: 'default.web',
                        description: null,
                        accessTokenTtlMinutes: 60,
                        refreshTokenTtlMinutes: 43200,
                        refreshTokenRotationEnabled: true,
                        pkceRequired: false,
                        reuseRefreshTokenForbidden: true,
                        isDefault: false,
                        isActive: true,
                        createdAt: '2026-03-25 17:00:00',
                        clientsCount: 0,
                        canDelete: true,
                        deleteBlockCode: null,
                        deleteBlockReason: null,
                    },
                    {
                        id: 2,
                        name: 'Strict Public',
                        code: 'public.strict',
                        description: null,
                        accessTokenTtlMinutes: 30,
                        refreshTokenTtlMinutes: 10080,
                        refreshTokenRotationEnabled: true,
                        pkceRequired: true,
                        reuseRefreshTokenForbidden: true,
                        isDefault: false,
                        isActive: true,
                        createdAt: '2026-03-25 17:00:00',
                        clientsCount: 0,
                        canDelete: true,
                        deleteBlockCode: null,
                        deleteBlockReason: null,
                    },
                ],
                filters: { global: null, status: null },
                pagination: { currentPage: 1, lastPage: 1, perPage: 10, total: 2, from: 1, to: 2, first: 0 },
                sorting: { field: 'name', order: 1 },
                canManageTokenPolicies: true,
            },
        });

        await nextTick();
        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        await checkboxes[1].setValue(true);

        const bulkDeleteButton = wrapper.findAll('button').find((button) => button.text() === 'Delete Selected');
        await bulkDeleteButton.trigger('click');
        expect(confirmRequire).toHaveBeenCalledTimes(1);
    });

    it('falls back to the previous page after deleting the last row on a page', async () => {
        const wrapper = mountPage(Index, {
            props: {
                rows: [{
                    id: 9,
                    name: 'Default Web Policy',
                    code: 'default.web',
                    description: 'Balanced default settings.',
                    accessTokenTtlMinutes: 60,
                    refreshTokenTtlMinutes: 43200,
                    refreshTokenRotationEnabled: true,
                    pkceRequired: false,
                    reuseRefreshTokenForbidden: true,
                    isDefault: false,
                    isActive: true,
                    createdAt: '2026-03-25 17:00:00',
                    clientsCount: 0,
                    canDelete: true,
                    deleteBlockCode: null,
                    deleteBlockReason: null,
                }],
                filters: { global: null, status: null },
                pagination: { currentPage: 2, lastPage: 2, perPage: 10, total: 11, from: 11, to: 11, first: 10 },
                sorting: { field: 'name', order: 1 },
                canManageTokenPolicies: true,
            },
        });

        await nextTick();

        const deleteButton = wrapper.findAll('button').find((button) => button.attributes('data-row-action') === 'Delete');
        await deleteButton.trigger('click');
        await confirmRequire.mock.calls[0][0].accept();

        expect(router.get).toHaveBeenLastCalledWith(
            route('admin.token-policies.index'),
            expect.objectContaining({
                page: 1,
                perPage: 10,
            }),
            expect.any(Object),
        );
    });

    it('submits the create page form', async () => {
        const wrapper = mountPage(Create);
        const form = getLastForm();

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
    });

    it('prefills and submits the edit page form', async () => {
        const wrapper = mountPage(Edit, {
            props: {
                tokenPolicy: {
                    id: 5,
                    name: 'Strict Public',
                    code: 'public.strict',
                    description: 'Requires PKCE.',
                    access_token_ttl_minutes: 30,
                    refresh_token_ttl_minutes: 10080,
                    refresh_token_rotation_enabled: true,
                    pkce_required: true,
                    reuse_refresh_token_forbidden: true,
                    is_default: false,
                    is_active: true,
                },
            },
        });
        const form = getLastForm();

        expect(form.code).toBe('public.strict');
        expect(form.pkce_required).toBe(true);

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('renders token policy form fields and updates booleans', async () => {
        const form = {
            name: '',
            code: '',
            description: '',
            access_token_ttl_minutes: 60,
            refresh_token_ttl_minutes: 30,
            refresh_token_rotation_enabled: false,
            pkce_required: false,
            reuse_refresh_token_forbidden: false,
            is_default: false,
            is_active: true,
            errors: {
                refresh_token_ttl_minutes: 'Refresh token TTL must be greater than or equal to access token TTL.',
            },
        };

        const wrapper = mount(TokenPolicyForm, {
            props: {
                form,
            },
        });

        expect(wrapper.text()).toContain('Basic Information');
        expect(wrapper.text()).toContain('TTL Settings');
        expect(wrapper.text()).toContain('Security Rules');
        expect(wrapper.text()).toContain('Refresh token TTL must be greater than or equal to access token TTL.');

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        await checkboxes[0].setValue(true);
        await checkboxes[1].setValue(true);

        expect(form.refresh_token_rotation_enabled).toBe(true);
        expect(form.pkce_required).toBe(true);
    });
});
