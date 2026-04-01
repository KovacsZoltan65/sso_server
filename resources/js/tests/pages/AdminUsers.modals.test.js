import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import CreateModal from '@/Pages/Admin/Users/CreateModal.vue';
import EditModal from '@/Pages/Admin/Users/EditModal.vue';
import UserFormFields from '@/Pages/Admin/Users/Partials/UserFormFields.vue';
import { getLastForm } from '@/tests/mocks/inertia';

describe('Admin Users modals', () => {
    it('submits the create modal and emits saved plus close', async () => {
        const wrapper = mount(CreateModal, {
            props: {
                visible: true,
                roleOptions: [{ label: 'Admin', value: 'admin' }],
            },
            global: {
                stubs: {
                    UserFormFields: true,
                },
            },
        });

        const form = getLastForm();

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'User created successfully.',
            type: 'create',
        });
        expect(form.is_active).toBe(true);
        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([false]);
    });

    it('loads the selected user into the edit modal before submit', async () => {
        const wrapper = mount(EditModal, {
            props: {
                visible: true,
                user: {
                    id: 7,
                    name: 'Taylor',
                    email: 'taylor@example.com',
                    isActive: false,
                    roles: ['admin'],
                },
                roleOptions: [{ label: 'Admin', value: 'admin' }],
            },
            global: {
                stubs: {
                    UserFormFields: true,
                },
            },
        });

        const form = getLastForm();

        expect(form.name).toBe('Taylor');
        expect(form.email).toBe('taylor@example.com');
        expect(form.is_active).toBe(false);
        expect(form.roles).toEqual(['admin']);

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('saved')?.[0]?.[0]).toEqual({
            message: 'User updated successfully.',
            type: 'edit',
        });
    });

    it('renders the shared user form fields with password inputs when requested', async () => {
        const form = {
            name: 'Taylor',
            email: 'taylor@example.com',
            is_active: true,
            roles: [],
            password: '',
            password_confirmation: '',
            errors: {
                email: 'Email is required.',
                is_active: 'The is active field must be true or false.',
            },
        };

        const wrapper = mount(UserFormFields, {
            props: {
                form,
                roleOptions: [{ label: 'Admin', value: 'admin' }],
                showPasswordFields: true,
            },
        });

        expect(wrapper.text()).toContain('Name');
        expect(wrapper.text()).toContain('Email');
        expect(wrapper.text()).toContain('Roles');
        expect(wrapper.text()).toContain('User is active');
        expect(wrapper.text()).toContain('Password');
        expect(wrapper.text()).toContain('Confirm password');
        expect(wrapper.text()).toContain('Email is required.');
        expect(wrapper.text()).toContain('The is active field must be true or false.');

        const select = wrapper.find('select');
        await select.setValue(['admin']);
        await nextTick();

        expect(form.roles).toEqual(['admin']);

        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(false);

        expect(form.is_active).toBe(false);
    });
});
