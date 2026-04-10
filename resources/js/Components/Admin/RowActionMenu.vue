<script setup>
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import { computed, ref } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const menu = ref(null);

const menuItems = computed(() => props.items
    .filter(Boolean)
    .map((item) => ({
        ...item,
        command: (event) => {
            if (item.disabled) {
                return;
            }

            item.command?.(event);
        },
    })));

const toggle = (event) => {
    if (props.disabled || menuItems.value.length === 0) {
        return;
    }

    menu.value?.toggle(event);
};
</script>

<template>
    <div class="relative flex justify-end">
        <Button
            icon="pi pi-ellipsis-v"
            severity="secondary"
            text
            rounded
            aria-label="Row actions"
            :disabled="disabled"
            @click="toggle"
        />

        <Menu
            ref="menu"
            :model="menuItems"
            popup
            appendTo="body"
            :pt="{
                root: {
                    class: 'min-w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-lg',
                },
                list: {
                    class: 'flex flex-col gap-1',
                },
                itemContent: {
                    class: 'rounded-lg',
                },
                itemLink: {
                    class: 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100',
                },
                itemIcon: {
                    class: 'text-slate-500',
                },
                itemLabel: {
                    class: 'text-sm',
                },
            }"
        />
    </div>
</template>
