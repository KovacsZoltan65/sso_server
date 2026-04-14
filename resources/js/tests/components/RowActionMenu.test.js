import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';

describe('RowActionMenu', () => {
    it('renders the explicit primary action directly and keeps secondary actions in the overflow menu', async () => {
        const editCommand = vi.fn();
        const deleteCommand = vi.fn();
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [
                    { label: 'Edit', icon: 'pi pi-pencil', isPrimary: true, command: editCommand },
                    { label: 'Delete', icon: 'pi pi-trash', isDangerous: true, command: deleteCommand },
                ],
            },
        });

        expect(wrapper.get('[data-primary-row-action="Edit"]').text()).toContain('Edit');

        await wrapper.get('button[aria-label="Row actions"]').trigger('click');
        await flushPromises();

        const popup = wrapper.get('[data-menu-popup="true"]');

        expect(popup.attributes('data-menu-append-to')).toBe('body');
        expect(popup.text()).toContain('Delete');
        expect(popup.text()).not.toContain('Edit');

        await popup.get('button').trigger('click');

        expect(deleteCommand).toHaveBeenCalledTimes(1);
        expect(editCommand).toHaveBeenCalledTimes(0);
    });

    it('prefers a non-dangerous fallback action as primary when none is explicitly marked', async () => {
        const viewCommand = vi.fn();
        const deleteCommand = vi.fn();
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [
                    { label: 'Delete', isDangerous: true, command: deleteCommand },
                    { label: 'View', command: viewCommand },
                ],
            },
        });

        await wrapper.get('[data-primary-row-action="View"]').trigger('click');

        expect(viewCommand).toHaveBeenCalledTimes(1);
        expect(deleteCommand).toHaveBeenCalledTimes(0);
    });

    it('does not promote a dangerous action to primary unless it is explicitly configured', () => {
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [{ label: 'Delete', isDangerous: true, command: vi.fn() }],
            },
        });

        expect(wrapper.find('[data-primary-row-action]').exists()).toBe(false);
        expect(wrapper.find('button[aria-label="Row actions"]').exists()).toBe(true);
    });

    it('does not open the popup when the trigger is disabled', async () => {
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [{ label: 'Delete', isDangerous: true, command: vi.fn() }],
                disabled: true,
            },
        });

        await wrapper.get('button[aria-label="Row actions"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-menu-popup="true"]').exists()).toBe(false);
    });

    it('renders a compact single primary action without an overflow trigger when no secondary action remains', () => {
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [{ label: 'Details', icon: 'pi pi-eye', isPrimary: true, command: vi.fn() }],
            },
        });

        expect(wrapper.find('[data-primary-row-action="Details"]').exists()).toBe(true);
        expect(wrapper.find('button[aria-label="Row actions"]').exists()).toBe(false);
    });
});
