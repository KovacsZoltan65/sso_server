<script setup>
import Card from 'primevue/card';

defineProps({
    stat: {
        type: Object,
        required: true,
    },
});

const toneClass = {
    blue: 'bg-sky-100 text-sky-700',
    slate: 'bg-slate-100 text-slate-700',
    amber: 'bg-amber-100 text-amber-700',
    emerald: 'bg-emerald-100 text-emerald-700',
};
</script>

<template>
    <Card class="surface-card h-full">
        <template #content>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-slate-500">{{ stat.label }}</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ stat.value }}</div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl" :class="toneClass[stat.tone]">
                    <i :class="[stat.icon, 'text-lg']"></i>
                </div>
            </div>
        </template>
    </Card>
</template>
