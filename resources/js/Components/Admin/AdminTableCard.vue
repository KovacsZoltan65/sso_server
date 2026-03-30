<script setup>
import Card from 'primevue/card';
</script>

<template>
    <Card
        :pt="{
            root: { class: 'admin-table-card surface-card flex min-h-0 flex-1 flex-col' },
            body: { class: 'flex min-h-0 flex-1 flex-col' },
            content: { class: 'flex min-h-0 flex-1 flex-col p-0' },
        }"
    >
        <template #content>
            <div class="admin-table-card__content flex min-h-0 flex-1 flex-col">
                <div class="admin-table-card__body flex min-h-0 flex-1 flex-col overflow-hidden">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="admin-table-card__footer flex-none border-t border-slate-200 px-6 py-4">
                    <slot name="footer" />
                </div>
            </div>
        </template>
    </Card>
</template>
