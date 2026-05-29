import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Index from '@/Pages/Admin/AuditLogs/Index.vue';
import { router } from '@/tests/mocks/inertia';
import { mountPage } from '@/tests/testUtils';

describe('Audit logs index', () => {
    const baseProps = {
        rows: [
            {
                id: 1,
                event: 'oauth.token.issued',
                logName: 'oauth',
                description: 'OAuth token issued.',
                severity: 'info',
                actor: {
                    id: 2,
                    name: 'Admin User',
                    email: 'admin@example.com',
                    label: 'Admin User (admin@example.com)',
                },
                client: {
                    id: 3,
                    name: 'Portal Client',
                    clientId: 'portal-client',
                    label: 'Portal Client (portal-client)',
                },
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 test agent',
                userAgentShort: 'Mozilla/5.0 test agent',
                createdAt: '2026-05-29 08:00:00',
                properties: {
                    client_id: 3,
                    access_token: '[masked]',
                },
                context: {
                    log_name: 'oauth',
                    subject_type: 'App\\Models\\SsoClient',
                    subject_id: 3,
                },
            },
        ],
        filters: {
            search: null,
            event: null,
            actor_id: null,
            client_id: null,
            severity: null,
            date_from: null,
            date_to: null,
        },
        sorting: {
            field: 'created_at',
            order: -1,
        },
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 15,
            total: 1,
            from: 1,
            to: 1,
            first: 0,
        },
        eventOptions: [
            { label: 'oauth.token.issued', value: 'oauth.token.issued' },
        ],
        severityOptions: [
            { label: 'Info', value: 'info' },
        ],
    };

    it('renders the audit log table and filters', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('Audit napló');
        expect(wrapper.find('[data-audit-search]').exists()).toBe(true);
        expect(wrapper.find('.datatable-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('oauth.token.issued');
        expect(wrapper.text()).toContain('Portal Client');
    });

    it('pushes search state into the server request', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await wrapper.find('[data-audit-search]').setValue('oauth.token');
        await wrapper.find('[data-audit-search]').trigger('keyup.enter');

        expect(router.get).toHaveBeenCalledWith(
            route('admin.audit-logs.index'),
            expect.objectContaining({
                search: 'oauth.token',
                page: 1,
            }),
            expect.any(Object),
        );
    });

    it('opens the details dialog with formatted masked payload', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-audit-details]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-audit-details-dialog]').exists()).toBe(true);
        expect(wrapper.text()).toContain('OAuth token issued.');
        expect(wrapper.text()).toContain('[masked]');
    });

    it('resets filters back to the default server query', async () => {
        const wrapper = mountPage(Index, {
            props: {
                ...baseProps,
                filters: {
                    ...baseProps.filters,
                    search: 'oauth',
                    severity: 'error',
                },
            },
        });

        await wrapper.find('[data-audit-reset]').trigger('click');

        expect(router.get).toHaveBeenCalledWith(
            route('admin.audit-logs.index'),
            expect.objectContaining({
                search: undefined,
                severity: undefined,
                sort_field: 'created_at',
                sort_order: -1,
            }),
            expect.any(Object),
        );
    });
});
