<script setup>
import Card from 'primevue/card';

defineProps({
    contentClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Card
        :pt="{
            root: { class: 'admin-form-card surface-card flex min-h-0 flex-1 flex-col overflow-hidden' },
            body: { class: 'admin-form-card__body flex min-h-0 flex-1 flex-col overflow-hidden' },
            content: { class: 'admin-form-card__content flex min-h-0 flex-1 flex-col overflow-hidden p-0' },
        }"
    >
        <template #content>
            <div v-if="$slots.header" class="flex-none border-b border-slate-200/80 px-6 py-5">
                <slot name="header" />
            </div>

            <div :class="['admin-form-card__scroll flex-1 overflow-y-auto px-6 py-5', contentClass]">
                <slot />
            </div>

            <div v-if="$slots.footer" class="flex-none border-t border-slate-200/80 px-6 py-4">
                <slot name="footer" />
            </div>
        </template>
    </Card>
</template>
