<script setup>
import Button from 'primevue/button';
import { trans } from 'laravel-vue-i18n';
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

const normalizedItems = computed(() => props.items.filter(Boolean));
const dangerousActionPattern = /(delete|remove|destroy|revoke|torles|torol|force)/i;

const isDangerousAction = (item) => {
    if (!item) {
        return false;
    }

    return item.isDangerous === true
        || item.severity === 'danger'
        || item.severity === 'warn'
        || dangerousActionPattern.test(`${item.key ?? ''} ${item.label ?? ''}`);
};

const wrapCommand = (item) => (event) => {
    if (item.disabled) {
        return;
    }

    item.command?.(event);
};

const primaryItem = computed(() => {
    const explicitPrimary = normalizedItems.value.find((item) => item.isPrimary);

    if (explicitPrimary) {
        return explicitPrimary;
    }

    return normalizedItems.value.find((item) => !isDangerousAction(item)) ?? null;
});

const overflowItems = computed(() => normalizedItems.value.filter((item) => item !== primaryItem.value));

const menuItems = computed(() => overflowItems.value.map((item) => ({
    ...item,
    command: wrapCommand(item),
})));

const primarySeverity = computed(() => {
    if (!primaryItem.value) {
        return 'secondary';
    }

    return primaryItem.value.severity ?? (isDangerousAction(primaryItem.value) ? 'warn' : 'secondary');
});

const toggle = (event) => {
    if (props.disabled || menuItems.value.length === 0) {
        return;
    }

    menu.value?.toggle(event);
};

const runPrimaryAction = (event) => {
    if (!primaryItem.value || props.disabled || primaryItem.value.disabled) {
        return;
    }

    wrapCommand(primaryItem.value)(event);
};
</script>

<template>
    <div class="relative flex items-center justify-end gap-1">
        <Button
            v-if="primaryItem"
            :label="primaryItem.label"
            :icon="primaryItem.icon"
            :severity="primarySeverity"
            outlined
            size="small"
            class="max-w-full whitespace-nowrap"
            :aria-label="primaryItem.ariaLabel ?? primaryItem.label"
            :disabled="disabled || primaryItem.disabled"
            :data-primary-row-action="primaryItem.label"
            data-testid="row-action-primary"
            @click="runPrimaryAction"
        />

        <Button
            v-if="menuItems.length > 0"
            icon="pi pi-ellipsis-v"
            severity="secondary"
            text
            rounded
            :aria-label="trans('table.row_actions')"
            :disabled="disabled"
            data-testid="row-action-overflow"
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
