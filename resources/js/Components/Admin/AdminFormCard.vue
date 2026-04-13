<script setup>
import Card from 'primevue/card';

defineProps({
    contentClass: {
        type: String,
        default: '',
    },
    grow: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <Card
        :pt="{
            root: { class: ['admin-form-card surface-card flex min-h-0 flex-col overflow-hidden', grow ? 'flex-1' : 'flex-none'] },
            body: { class: ['admin-form-card__body flex min-h-0 flex-col overflow-hidden', grow ? 'flex-1' : 'flex-none'] },
            content: { class: ['admin-form-card__content flex min-h-0 flex-col overflow-hidden p-0', grow ? 'flex-1' : 'flex-none'] },
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
