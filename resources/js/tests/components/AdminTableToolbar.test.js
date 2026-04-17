import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';

describe('AdminTableToolbar', () => {
    it('keeps bulk delete disabled when there is no selection and emits when selection exists', async () => {
        const wrapper = mount(AdminTableToolbar, {
            props: {
                canBulkDelete: true,
                selectedCount: 0,
                selectableCount: 5,
                busy: false,
            },
        });

        const bulkButton = wrapper.get('[data-toolbar-action="bulk-delete"]');
        expect(bulkButton.attributes('disabled')).toBeDefined();

        await wrapper.setProps({ selectedCount: 2 });
        await bulkButton.trigger('click');

        expect(wrapper.emitted('bulk-delete')).toHaveLength(1);
    });

    it('disables refresh and create actions while busy', () => {
        const wrapper = mount(AdminTableToolbar, {
            props: {
                canCreate: true,
                busy: true,
            },
        });

        expect(wrapper.get('[data-toolbar-action="refresh"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-toolbar-action="create"]').attributes('disabled')).toBeDefined();
    });
});
