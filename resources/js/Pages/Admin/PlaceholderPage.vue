<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { adminPlaceholders } from '@/Services/adminPages';
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Timeline from 'primevue/timeline';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
});

const items = computed(() =>
    (adminPlaceholders[props.page.key] ?? []).map((label, index) => ({
        id: index + 1,
        label,
    })),
);
</script>

<template>
    <Head :title="page.label" />

    <AuthenticatedLayout>
        <PageHeader :title="page.label" :description="page.description" />

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <Card class="surface-card">
                <template #title>Module status</template>
                <template #content>
                    <div class="space-y-4">
                        <Tag value="Foundation placeholder" severity="secondary" />
                        <p class="section-copy">
                            This module is intentionally kept lightweight during bootstrap. Routing, authorization, menu placement, and visual shell are ready for the full SSO implementation.
                        </p>
                        <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            Integration target: <span class="font-semibold text-slate-900">{{ page.label }}</span>
                        </div>
                    </div>
                </template>
            </Card>

            <Card class="surface-card">
                <template #title>Next implementation steps</template>
                <template #content>
                    <Timeline :value="items" align="left">
                        <template #content="{ item }">
                            <div class="pb-5 text-sm leading-7 text-slate-600">{{ item.label }}</div>
                        </template>
                    </Timeline>
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
