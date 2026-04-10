import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';

describe('RowActionMenu', () => {
    it('opens a popup menu anchored from the shared trigger button', async () => {
        const command = vi.fn();
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [{ label: 'Edit', command }],
            },
        });

        expect(wrapper.find('[data-menu-popup="true"]').exists()).toBe(false);

        await wrapper.get('button[aria-label="Row actions"]').trigger('click');
        await flushPromises();

        const popup = wrapper.get('[data-menu-popup="true"]');

        expect(popup.attributes('data-menu-append-to')).toBe('body');
        expect(popup.text()).toContain('Edit');

        await popup.get('button').trigger('click');

        expect(command).toHaveBeenCalledTimes(1);
    });

    it('does not open the popup when the trigger is disabled', async () => {
        const wrapper = mount(RowActionMenu, {
            props: {
                items: [{ label: 'Delete', command: vi.fn() }],
                disabled: true,
            },
        });

        await wrapper.get('button[aria-label="Row actions"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-menu-popup="true"]').exists()).toBe(false);
    });
});
