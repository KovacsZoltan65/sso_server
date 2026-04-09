import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ProfileEditPage from '@/Pages/Profile/Edit.vue';

describe('Profile/Edit', () => {
    it('renders the three self-service profile sections and passes the page props through', () => {
        const wrapper = mount(ProfileEditPage, {
            props: {
                mustVerifyEmail: true,
                status: 'verification-link-sent',
            },
            global: {
                stubs: {
                    AuthenticatedLayout: {
                        template: '<div><slot name="header" /><slot /></div>',
                    },
                    UpdateProfileInformationForm: {
                        props: ['mustVerifyEmail', 'status'],
                        template: '<section data-profile-information :data-must-verify-email="String(mustVerifyEmail)" :data-status="status"></section>',
                    },
                    UpdatePasswordForm: {
                        template: '<section data-password-form></section>',
                    },
                    DeleteUserForm: {
                        template: '<section data-delete-user-form></section>',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Profile');
        expect(wrapper.get('[data-profile-information]').attributes('data-must-verify-email')).toBe('true');
        expect(wrapper.get('[data-profile-information]').attributes('data-status')).toBe('verification-link-sent');
        expect(wrapper.get('[data-password-form]').exists()).toBe(true);
        expect(wrapper.get('[data-delete-user-form]').exists()).toBe(true);
    });

    it('passes a null status through to the profile information form without crashing', () => {
        const wrapper = mount(ProfileEditPage, {
            props: {
                mustVerifyEmail: false,
                status: null,
            },
            global: {
                stubs: {
                    AuthenticatedLayout: {
                        template: '<div><slot name="header" /><slot /></div>',
                    },
                    UpdateProfileInformationForm: {
                        props: ['mustVerifyEmail', 'status'],
                        template: '<section data-profile-information :data-must-verify-email="String(mustVerifyEmail)" :data-status="status === null ? \'null\' : status"></section>',
                    },
                    UpdatePasswordForm: {
                        template: '<section data-password-form></section>',
                    },
                    DeleteUserForm: {
                        template: '<section data-delete-user-form></section>',
                    },
                },
            },
        });

        expect(wrapper.get('[data-profile-information]').attributes('data-must-verify-email')).toBe('false');
        expect(wrapper.get('[data-profile-information]').attributes('data-status')).toBe('null');
    });
});
