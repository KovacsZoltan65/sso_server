import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ProfileEditPage from '@/Pages/Profile/Edit.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { getLastForm, setPageProps } from '@/tests/mocks/inertia';
import { toastAdd } from '@/tests/mocks/primevue';

describe('Profile/Edit', () => {
    it('renders the profile page inside the admin shell with card-based sections', () => {
        const wrapper = mount(ProfileEditPage, {
            props: {
                mustVerifyEmail: true,
                status: 'verification-link-sent',
            },
            global: {
                stubs: {
                    AuthenticatedLayout: {
                        template: '<div data-layout="authenticated"><slot /></div>',
                    },
                    PageHeader: {
                        props: ['title', 'description'],
                        template: '<section data-page-header><h1>{{ title }}</h1><p>{{ description }}</p></section>',
                    },
                    AdminFormCard: {
                        props: ['grow'],
                        template: '<section class="admin-form-card-stub" :data-grow="grow === false ? \'false\' : \'true\'"><slot name="header" /><slot /></section>',
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

        expect(wrapper.get('[data-layout="authenticated"]').exists()).toBe(true);
        expect(wrapper.get('[data-page-header]').text()).toContain('Profil');
        expect(wrapper.findAll('.admin-form-card-stub')).toHaveLength(3);
        expect(wrapper.findAll('.admin-form-card-stub').map((card) => card.attributes('data-grow'))).toEqual(['true', 'true', 'false']);
        expect(wrapper.get('[data-profile-information]').attributes('data-must-verify-email')).toBe('true');
        expect(wrapper.get('[data-profile-information]').attributes('data-status')).toBe('verification-link-sent');
        expect(wrapper.get('[data-password-form]').exists()).toBe(true);
        expect(wrapper.get('[data-delete-user-form]').exists()).toBe(true);
    });

    it('submits the profile information form and shows a success toast', async () => {
        setPageProps({
            auth: {
                user: {
                    name: 'Jane Doe',
                    email: 'jane@example.test',
                    email_verified_at: '2026-04-01 10:00:00',
                },
            },
            flash: {},
        });

        const wrapper = mount(UpdateProfileInformationForm, {
            props: {
                mustVerifyEmail: false,
                status: null,
            },
            global: {
                stubs: {
                    Link: {
                        template: '<button type="button"><slot /></button>',
                    },
                },
            },
        });

        const form = getLastForm();

        await wrapper.get('#profile-name').setValue('Jane Admin');
        await wrapper.get('button').trigger('click');

        expect(form.patch).toHaveBeenCalledTimes(1);
        expect(form.patch.mock.calls[0][0]).toBe(JSON.stringify({ name: 'profile.update' }));

        form.patch.mock.calls[0][1].onSuccess();

        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            summary: 'Profil mentve',
        }));
    });

    it('submits the password form and shows a success toast', async () => {
        const wrapper = mount(UpdatePasswordForm);
        const form = getLastForm();

        await wrapper.get('#current_password').setValue('old-secret');
        await wrapper.get('#password').setValue('new-secret-123');
        await wrapper.get('#password_confirmation').setValue('new-secret-123');
        await wrapper.get('button').trigger('click');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put.mock.calls[0][0]).toBe(JSON.stringify({ name: 'password.update' }));

        form.put.mock.calls[0][1].onSuccess();

        expect(form.reset).toHaveBeenCalled();
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            summary: 'Jelszo frissitve',
        }));
    });
});
