import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Index from '@/Pages/RememberedConsents/Index.vue';
import { axiosPost } from '@/tests/mocks/axios';
import { confirmRequire, toastAdd } from '@/tests/mocks/primevue';
import { router } from '@/tests/mocks/inertia';
import { mountPage } from '@/tests/testUtils';

describe('Remembered consents index', () => {
    const baseProps = {
        rows: [
            {
                id: 41,
                userId: 1,
                userName: 'Consent Owner',
                userEmail: 'owner@example.com',
                clientId: 11,
                clientName: 'Portal Client',
                clientPublicId: 'portal-client',
                scopeCodes: ['openid', 'profile'],
                trustTierSnapshot: 'first_party_untrusted',
                status: 'active',
                grantedAt: '2026-04-04 10:00:00',
                expiresAt: '2026-05-04T10:00:00+00:00',
                revokedAt: null,
                revocationReason: null,
                canRevoke: true,
            },
            {
                id: 42,
                userId: 2,
                userName: 'Revoked Owner',
                userEmail: 'revoked@example.com',
                clientId: 12,
                clientName: 'Legacy Client',
                clientPublicId: 'legacy-client',
                scopeCodes: ['openid'],
                trustTierSnapshot: 'third_party',
                status: 'revoked',
                grantedAt: '2026-04-03 10:00:00',
                expiresAt: '2026-05-03T10:00:00+00:00',
                revokedAt: '2026-04-04T09:00:00+00:00',
                revocationReason: 'security_incident',
                canRevoke: false,
            },
        ],
        filters: {
            global: null,
            client_id: null,
            user_id: null,
            status: null,
        },
        sorting: {
            field: 'grantedAt',
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
            { id: 12, name: 'Legacy Client', clientId: 'legacy-client' },
        ],
        userOptions: [
            { id: 1, name: 'Consent Owner', email: 'owner@example.com' },
            { id: 2, name: 'Revoked Owner', email: 'revoked@example.com' },
        ],
        revocationReasonOptions: [
            { label: 'Admin Manual Revoke', value: 'admin_manual_revoke' },
            { label: 'Security Incident', value: 'security_incident' },
        ],
        canManageRememberedConsents: true,
    };

    it('renders statuses, scope tags, and revoke reasons', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('Portal Client');
        expect(wrapper.text()).toContain('Consent Owner');
        expect(wrapper.text()).toContain('openid');
        expect(wrapper.text()).toContain('profile');
        expect(wrapper.text()).toContain('active');
        expect(wrapper.text()).toContain('revoked');
        expect(wrapper.text()).toContain('security_incident');
    });

    it('pushes filter state into the server request', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        const selects = wrapper.findAll('select');
        await selects[0].setValue('revoked');
        await nextTick();

        expect(router.get).toHaveBeenCalledWith(
            route('admin.remembered-consents.index'),
            expect.objectContaining({
                status: 'revoked',
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
        await nextTick();

        await wrapper.find('[data-revoke-reason]').setValue('security_incident');
        await wrapper.find('[data-revoke-submit]').trigger('click');

        expect(confirmRequire).toHaveBeenCalledTimes(1);

        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(axiosPost).toHaveBeenCalledWith(
            route('admin.remembered-consents.revoke', 41),
            {
                revocation_reason: 'security_incident',
            },
        );

        expect(router.get).toHaveBeenCalledWith(
            route('admin.remembered-consents.index'),
            expect.any(Object),
            expect.any(Object),
        );

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Remembered consent revoked successfully.',
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
        await nextTick();
        await wrapper.find('[data-revoke-submit]').trigger('click');
        await confirmRequire.mock.calls[0][0].accept();
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Forbidden.',
        }));
    });
});
