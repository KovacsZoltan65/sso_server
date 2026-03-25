<script setup>
import Button from 'primevue/button';
import { computed, nextTick, onBeforeUnmount, ref, Teleport, watch } from 'vue';

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

const root = ref(null);
const isOpen = ref(false);
const panelStyle = ref({});

const menuItems = computed(() => props.items
    .filter(Boolean));

const close = () => {
    isOpen.value = false;
};

const updatePanelPosition = () => {
    if (!root.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();

    panelStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${Math.max(12, rect.right - 176)}px`,
        zIndex: '1200',
    };
};

const toggle = async () => {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick();
        updatePanelPosition();
    }
};

const handleItemClick = (item) => {
    if (item.disabled) {
        return;
    }

    close();
    item.command?.();
};

const handleDocumentClick = (event) => {
    if (!isOpen.value) {
        return;
    }

    if (root.value?.contains(event.target)) {
        return;
    }

    close();
};

const handleWindowChange = () => {
    if (!isOpen.value) {
        return;
    }

    updatePanelPosition();
};

watch(isOpen, (open) => {
    if (open) {
        document.addEventListener('click', handleDocumentClick);
        window.addEventListener('resize', handleWindowChange);
        window.addEventListener('scroll', handleWindowChange, true);

        return;
    }

    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('resize', handleWindowChange);
    window.removeEventListener('scroll', handleWindowChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('resize', handleWindowChange);
    window.removeEventListener('scroll', handleWindowChange, true);
});
</script>

<template>
    <div ref="root" class="relative flex justify-end">
        <Button
            icon="pi pi-ellipsis-v"
            severity="secondary"
            text
            rounded
            aria-label="Row actions"
            :disabled="disabled"
            @click="toggle"
        />
    </div>

    <Teleport to="body">
        <div
            v-if="isOpen"
            class="min-w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
            :style="panelStyle"
        >
            <Button
                v-for="item in menuItems"
                :key="item.label"
                :label="item.label"
                :icon="item.icon"
                severity="secondary"
                text
                class="w-full justify-start"
                :disabled="item.disabled"
                @click="handleItemClick(item)"
            />
        </div>
    </Teleport>
</template>
