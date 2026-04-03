import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LoginPage from '@/Pages/Auth/Login.vue';
import { getLastForm, setPageProps } from '@/tests/mocks/inertia';

describe('Auth/Login', () => {
    it('starts with empty credentials, removes demo hints, and preserves the submit flow', async () => {
        setPageProps({
            flash: {},
        });

        const wrapper = mount(LoginPage, {
            props: {
                canResetPassword: false,
                status: null,
            },
            global: {
                stubs: {
                    GuestLayout: {
                        props: ['title', 'description'],
                        template: '<div><slot /></div>',
                    },
                    Link: {
                        template: '<a><slot /></a>',
                    },
                },
            },
        });

        const form = getLastForm();

        expect(form.email).toBe('');
        expect(form.password).toBe('');
        expect(wrapper.text()).not.toContain('superadmin@sso.test');
        expect(wrapper.text()).not.toContain('Seeded accounts');
        expect(wrapper.text()).not.toContain('admin shell');

        await wrapper.get('#email').setValue('user@example.com');
        await wrapper.get('#password').setValue('secret');
        await wrapper.get('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post.mock.calls[0][0]).toBe(JSON.stringify({ name: 'login' }));

        form.post.mock.calls[0][1].onFinish();

        expect(form.reset).toHaveBeenCalledWith('password');
    });
});
