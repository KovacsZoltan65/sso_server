import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import DataTable from 'primevue/datatable';
import BaseDataTable from '@/Components/Admin/BaseDataTable.vue';

describe('BaseDataTable', () => {
    it('forwards update:selection events from the underlying table', async () => {
        const wrapper = mount(BaseDataTable, {
            props: {
                value: [{ id: 1, name: 'First' }],
            },
        });

        wrapper.findComponent(DataTable).vm.$emit('update:selection', [{ id: 1, name: 'First' }]);
        await nextTick();

        expect(wrapper.emitted('update:selection')).toEqual([
            [[{ id: 1, name: 'First' }]],
        ]);
    });

    it('forwards empty selection updates without transformation', async () => {
        const wrapper = mount(BaseDataTable, {
            props: {
                value: [{ id: 1, name: 'First' }],
            },
        });

        wrapper.findComponent(DataTable).vm.$emit('update:selection', null);
        await nextTick();

        expect(wrapper.emitted('update:selection')).toEqual([
            [null],
        ]);
    });
});
