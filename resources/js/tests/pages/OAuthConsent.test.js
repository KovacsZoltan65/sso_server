import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ConsentPage from '@/Pages/OAuth/Consent.vue';
import { getForms, getLastForm } from '@/tests/mocks/inertia';

describe('OAuth/Consent', () => {
    it('renders the public consent summary without exposing raw authorize internals', () => {
        const wrapper = mount(ConsentPage, {
            props: {
                consentToken: 'secret-server-side-token',
                client: {
                    name: 'Portal Client',
                    description: 'Portal access for company users.',
                    originHost: 'portal.example.com',
                    returnPath: '/auth/sso/callback',
                    trustLabel: 'Third-party application',
                    trustDescription: 'Registered outside the core first-party trust tier, so review the requested access carefully.',
                },
                scopes: [
                    {
                        code: 'openid',
                        name: 'OpenID',
                        description: 'Identify your account.',
                    },
                    {
                        code: 'profile',
                        name: 'Profile',
                        description: 'Read your basic profile details.',
                    },
                ],
                summary: {
                    title: 'Portal Client is requesting access to your account.',
                    description: 'Review the requested permissions before deciding whether to continue.',
                },
            },
            global: {
                stubs: {
                    PublicAuthLayout: {
                        props: ['title', 'description'],
                        template: '<div data-layout="public-auth"><slot /></div>',
                    },
                },
            },
        });

        expect(wrapper.find('[data-layout="public-auth"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Portal Client');
        expect(wrapper.text()).toContain('Third-party application');
        expect(wrapper.text()).toContain('portal.example.com');
        expect(wrapper.text()).toContain('/auth/sso/callback');
        expect(wrapper.text()).toContain('OpenID');
        expect(wrapper.text()).toContain('Profile');
        expect(wrapper.text()).toContain('Approve');
        expect(wrapper.text()).toContain('Deny');
        expect(wrapper.text()).toContain('Approve to continue back to portal.example.com/auth/sso/callback.');
        expect(wrapper.text()).toContain('Deny to return safely to portal.example.com without sharing these permissions.');
        expect(wrapper.text()).not.toContain('secret-server-side-token');
        expect(wrapper.text()).not.toContain('code_challenge');
    });

    it('posts only the consent token when approve is submitted', async () => {
        const wrapper = mount(ConsentPage, {
            props: {
                consentToken: 'a'.repeat(64),
                client: {
                    name: 'Portal Client',
                    description: 'Portal access for company users.',
                    originHost: 'portal.example.com',
                    returnPath: '/auth/sso/callback',
                    trustLabel: 'Third-party application',
                    trustDescription: 'Registered outside the core first-party trust tier, so review the requested access carefully.',
                },
                scopes: [
                    {
                        code: 'openid',
                        name: 'OpenID',
                        description: 'Identify your account.',
                    },
                ],
                summary: {
                    title: 'Portal Client is requesting access to your account.',
                    description: 'Review the requested permissions before deciding whether to continue.',
                },
            },
            global: {
                stubs: {
                    PublicAuthLayout: {
                        props: ['title', 'description'],
                        template: '<div data-layout="public-auth"><slot /></div>',
                    },
                },
            },
        });

        const forms = getForms();
        const form = forms[0];

        expect(forms).toHaveLength(2);
        expect(form.consent_token).toBe('a'.repeat(64));
        expect('redirect_uri' in form).toBe(false);
        expect('client_id' in form).toBe(false);
        expect('scope' in form).toBe(false);

        await wrapper.get('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post.mock.calls[0][0]).toBe(JSON.stringify({ name: 'oauth.authorize.approve' }));
    });

    it('posts only the consent token when deny is submitted and uses a separate endpoint', async () => {
        const wrapper = mount(ConsentPage, {
            props: {
                consentToken: 'b'.repeat(64),
                client: {
                    name: 'Portal Client',
                    description: 'Portal access for company users.',
                    originHost: 'portal.example.com',
                    returnPath: '/auth/sso/callback',
                    trustLabel: 'Third-party application',
                    trustDescription: 'Registered outside the core first-party trust tier, so review the requested access carefully.',
                },
                scopes: [
                    {
                        code: 'openid',
                        name: 'OpenID',
                        description: 'Identify your account.',
                    },
                ],
                summary: {
                    title: 'Portal Client is requesting access to your account.',
                    description: 'Review the requested permissions before deciding whether to continue.',
                },
            },
            global: {
                stubs: {
                    PublicAuthLayout: {
                        props: ['title', 'description'],
                        template: '<div data-layout="public-auth"><slot /></div>',
                    },
                },
            },
        });

        const forms = getForms();
        const approveForm = forms[0];
        const denyForm = forms[1];

        expect(forms).toHaveLength(2);
        expect(approveForm.consent_token).toBe('b'.repeat(64));
        expect(denyForm.consent_token).toBe('b'.repeat(64));
        expect('redirect_uri' in denyForm).toBe(false);
        expect('client_id' in denyForm).toBe(false);
        expect('scope' in denyForm).toBe(false);

        await wrapper.findAll('form')[1].trigger('submit.prevent');

        expect(denyForm.post).toHaveBeenCalledTimes(1);
        expect(denyForm.post.mock.calls[0][0]).toBe(JSON.stringify({ name: 'oauth.authorize.deny' }));
        expect(approveForm.post).not.toHaveBeenCalled();
    });
});
