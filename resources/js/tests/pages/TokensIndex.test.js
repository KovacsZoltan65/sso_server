import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Index from '@/Pages/Tokens/Index.vue';
import { axiosPost } from '@/tests/mocks/axios';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { router } from '@/tests/mocks/inertia';
import { mountPage } from '@/tests/testUtils';

describe('Tokens index', () => {
    const baseProps = {
        rows: [
            {
                id: 10,
                tokenType: 'refresh_token',
                userId: 1,
                userName: 'Active User',
                userEmail: 'active@example.com',
                clientId: 11,
                clientName: 'Portal Client',
                clientPublicId: 'portal-client',
                status: 'suspicious',
                familyId: 'family-1234567890',
                parentTokenId: null,
                replacedByTokenId: null,
                suspiciousIncident: true,
                familyRevoked: false,
                issuedAt: '2026-04-01 10:00:00',
                expiresAt: '2026-04-02T10:00:00+00:00',
                revokedAt: null,
                canRevoke: true,
                canRevokeFamily: true,
            },
            {
                id: 11,
                tokenType: 'refresh_token',
                userId: 2,
                userName: 'Revoked User',
                userEmail: 'revoked@example.com',
                clientId: 12,
                clientName: 'Legacy Portal',
                clientPublicId: 'legacy-client',
                status: 'family_revoked',
                familyId: 'family-rotated',
                parentTokenId: 8,
                replacedByTokenId: 12,
                suspiciousIncident: false,
                familyRevoked: true,
                issuedAt: '2026-04-01 09:00:00',
                expiresAt: '2026-04-02T09:00:00+00:00',
                revokedAt: '2026-04-01T11:00:00+00:00',
                canRevoke: false,
                canRevokeFamily: true,
            },
        ],
        filters: {
            global: null,
            token_type: 'refresh_token',
            state: null,
            client_id: null,
            user_id: null,
        },
        sorting: {
            field: 'createdAt',
            order: -1,
        },
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 10,
            total: 2,
            from: 1,
            to: 2,
            first: 0,
        },
        clientOptions: [
            { id: 11, name: 'Portal Client', clientId: 'portal-client' },
            { id: 12, name: 'Legacy Portal', clientId: 'legacy-client' },
        ],
        userOptions: [
            { id: 1, name: 'Active User', email: 'active@example.com' },
            { id: 2, name: 'Revoked User', email: 'revoked@example.com' },
        ],
        canManageTokens: true,
        canManageTokenFamilies: true,
    };

    it('renders token statuses and family chain metadata', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('Suspicious');
        expect(wrapper.text()).toContain('Family Revoked');
        expect(wrapper.text()).toContain('Incident');
        expect(wrapper.text()).toContain('family-12345...');
        expect(wrapper.text()).toContain('Replaced by #12');
        expect(wrapper.text()).toContain('Family revoked');
    });

    it('pushes filter state into the server request', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        const selects = wrapper.findAll('select');
        await selects[1].setValue('revoked');
        await nextTick();

        expect(router.get).toHaveBeenCalledWith(
            route('admin.tokens.index'),
            expect.objectContaining({
                state: 'revoked',
                token_type: 'refresh_token',
            }),
            expect.any(Object),
        );
    });

    it('sends the revoke request after confirmation and refreshes the table', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        await wrapper.find('[data-row-action="Revoke"]').trigger('click');

        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(axiosPost).toHaveBeenCalledWith(
            route('admin.tokens.revoke', 10),
            {
                token_type: 'refresh_token',
                reason: 'admin_revoked',
            },
        );

        expect(router.get).toHaveBeenCalledWith(
            route('admin.tokens.index'),
            expect.any(Object),
            expect.any(Object),
        );

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Token revoked successfully.',
        }));
    });

    it('opens the family dialog, sends the reason, and refreshes after family revoke', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-row-action="Revoke Family"]').trigger('click');
        await nextTick();

        const input = wrapper.find('[data-family-reason]');
        await input.setValue('manual_security_action');
        await wrapper.find('[data-family-submit]').trigger('click');

        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(axiosPost).toHaveBeenCalledWith(
            route('admin.tokens.revoke-family', 'family-1234567890'),
            {
                reason: 'manual_security_action',
            },
        );

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Token family revoked successfully.',
        }));
    });

    it('shows an error toast when revoke fails with a safe backend message', async () => {
        axiosPost.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Forbidden.',
                },
            },
        });

        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-row-action="Revoke"]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Forbidden.',
        }));
    });

    it('shows family revoke validation errors safely', async () => {
        axiosPost.mockRejectedValueOnce({
            response: {
                data: {
                    errors: {
                        reason: ['The reason field must not be greater than 255 characters.'],
                    },
                },
            },
        });

        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-row-action="Revoke Family"]').trigger('click');
        await nextTick();
        await wrapper.find('[data-family-reason]').setValue('x');
        await wrapper.find('[data-family-submit]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'The reason field must not be greater than 255 characters.',
        }));
    });

    it('refreshes from the shared toolbar', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-toolbar-action="refresh"]').trigger('click');

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'tokens refreshed successfully.',
        }));
    });
});
