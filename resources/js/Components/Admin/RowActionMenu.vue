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
const panel = ref(null);
const isOpen = ref(false);
const panelStyle = ref({});

const menuItems = computed(() => props.items
    .filter(Boolean));

const close = () => {
    isOpen.value = false;
};

const updatePanelPosition = () => {
    if (!root.value || !panel.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();
    const panelWidth = panel.value.offsetWidth || 176;
    const panelHeight = panel.value.offsetHeight || 0;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const fitsBelow = rect.bottom + 8 + panelHeight <= viewportHeight - 12;
    const top = fitsBelow
        ? rect.bottom + 8
        : Math.max(12, rect.top - panelHeight - 8);
    const left = Math.min(
        viewportWidth - panelWidth - 12,
        Math.max(12, rect.right - panelWidth),
    );

    panelStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
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
            ref="panel"
            class="flex min-w-40 flex-col items-stretch rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
            :style="panelStyle"
        >
            <Button
                v-for="item in menuItems"
                :key="item.label"
                :label="item.label"
                :icon="item.icon"
                severity="secondary"
                text
                class="w-full !justify-start !text-left"
                :disabled="item.disabled"
                @click="handleItemClick(item)"
            />
        </div>
    </Teleport>
</template>
