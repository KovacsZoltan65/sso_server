import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Index from '@/Pages/AuditLogs/Index.vue';
import { axiosGet } from '@/tests/mocks/axios';
import { router } from '@/tests/mocks/inertia';
import { toastAdd } from '@/tests/mocks/primevue';
import { mountPage } from '@/tests/testUtils';

describe('Audit logs index', () => {
    const baseProps = {
        rows: [
            {
                id: 41,
                eventType: 'security.authorization.denied',
                category: 'security',
                severity: 'critical',
                actor: {
                    type: 'User',
                    id: 9,
                    display: 'Security Admin (security@example.com)',
                },
                subject: {
                    type: 'SsoClient',
                    id: 7,
                    display: 'Portal (portal-client)',
                },
                client: {
                    id: 7,
                    display: 'Portal',
                    clientId: 'portal-client',
                },
                ipAddress: '127.0.0.1',
                occurredAt: '2026-04-01 10:15:00',
                summary: 'Authorization denied.',
            },
        ],
        filters: {
            global: null,
            event_type: null,
            category: null,
            severity: null,
            actor_type: null,
            subject_type: null,
            client_id: null,
            date_from: null,
            date_to: null,
        },
        sorting: {
            field: 'occurred_at',
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
        filterOptions: {
            categories: [
                { label: 'security', value: 'security' },
            ],
            severities: [
                { label: 'Critical', value: 'critical' },
            ],
            actorTypes: [
                { label: 'User', value: 'App\\Models\\User' },
            ],
            subjectTypes: [
                { label: 'SsoClient', value: 'App\\Models\\SsoClient' },
            ],
            clients: [
                { label: 'Portal (portal-client)', value: 7 },
            ],
        },
    };

    it('renders audit rows and severity tags', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        expect(wrapper.text()).toContain('security.authorization.denied');
        expect(wrapper.text()).toContain('Critical');
        expect(wrapper.text()).toContain('Security Admin (security@example.com)');
        expect(wrapper.text()).toContain('Portal (portal-client)');
    });

    it('pushes filter state into the inertia request', async () => {
        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();

        const selects = wrapper.findAll('select');
        await selects[1].setValue('critical');
        await nextTick();

        expect(router.get).toHaveBeenCalledWith(
            route('admin.audit-logs.index'),
            expect.objectContaining({
                severity: 'critical',
            }),
            expect.any(Object),
        );
    });

    it('loads details and renders sanitized meta in the dialog', async () => {
        axiosGet.mockResolvedValueOnce({
            data: {
                message: 'Audit log retrieved successfully.',
                data: {
                    id: 41,
                    eventType: 'security.authorization.denied',
                    category: 'security',
                    severity: 'critical',
                    actor: {
                        type: 'User',
                        id: 9,
                        display: 'Security Admin (security@example.com)',
                    },
                    subject: {
                        type: 'SsoClient',
                        id: 7,
                        display: 'Portal (portal-client)',
                    },
                    client: {
                        id: 7,
                        display: 'Portal',
                        clientId: 'portal-client',
                    },
                    ipAddress: '127.0.0.1',
                    userAgent: 'Vitest',
                    requestId: 'req-123',
                    occurredAt: '2026-04-01 10:15:00',
                    summary: 'Authorization denied.',
                    meta: {
                        authorization: '[REDACTED]',
                        nested: {
                            access_token: '[REDACTED]',
                            safe: 'visible',
                        },
                    },
                    tags: [],
                },
                meta: {},
                errors: {},
            },
        });

        const wrapper = mountPage(Index, {
            props: baseProps,
        });

        await nextTick();
        await wrapper.find('[data-row-action="Details"]').trigger('click');
        await nextTick();

        expect(axiosGet).toHaveBeenCalledWith(route('api.admin.audit-logs.show', 41));
        expect(wrapper.find('[data-audit-detail]').exists()).toBe(true);
        expect(wrapper.find('[data-audit-meta]').text()).toContain('"authorization": "[REDACTED]"');
        expect(wrapper.find('[data-audit-meta]').text()).toContain('"safe": "visible"');
    });

    it('shows a safe error toast when detail loading fails', async () => {
        axiosGet.mockRejectedValueOnce({
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
        await wrapper.find('[data-row-action="Details"]').trigger('click');
        await nextTick();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Forbidden.',
        }));
    });
});
