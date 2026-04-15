<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';

defineProps({
    stats: {
        type: Array,
        required: true,
    },
    recentActivity: {
        type: Array,
        required: true,
    },
    permissionGroups: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="trans('navigation.dashboard.label')" />

    <AuthenticatedLayout>
        <PageHeader
            :title="trans('navigation.dashboard.label')"
            :description="trans('pages.dashboard.description')"
        />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard v-for="stat in stats" :key="stat.label" :stat="stat" />
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <Card class="surface-card">
                <template #title>{{ trans('pages.dashboard.recent_activity_title') }}</template>
                <template #subtitle>{{ trans('pages.dashboard.recent_activity_subtitle') }}</template>
                <template #content>
                    <div v-if="recentActivity.length" class="space-y-4">
                        <div
                            v-for="entry in recentActivity"
                            :key="entry.id"
                            class="flex items-start justify-between gap-4 rounded-2xl border border-slate-100 px-4 py-4"
                        >
                            <div>
                                <div class="font-semibold text-slate-900">{{ entry.description }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ entry.causer }} · {{ entry.createdAt }}
                                </div>
                            </div>
                            <Tag :value="entry.event ?? entry.logName" severity="secondary" />
                        </div>
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-slate-200 px-5 py-10 text-sm text-slate-500">
                        {{ trans('pages.dashboard.no_activity') }}
                    </div>
                </template>
            </Card>

            <div class="space-y-6">
                <Card class="surface-card">
                    <template #title>{{ trans('pages.dashboard.quick_actions_title') }}</template>
                    <template #content>
                        <div class="space-y-3">
                            <Link :href="route('admin.users.index')" class="block">
                                <Button :label="trans('pages.dashboard.open_users')" icon="pi pi-users" class="w-full justify-center" />
                            </Link>
                            <Link :href="route('admin.sso-clients.index')" class="block">
                                <Button
                                    :label="trans('pages.dashboard.review_client_placeholder')"
                                    icon="pi pi-desktop"
                                    severity="secondary"
                                    class="w-full justify-center"
                                />
                            </Link>
                        </div>
                    </template>
                </Card>

                <Card class="surface-card">
                    <template #title>{{ trans('pages.dashboard.permission_layout_title') }}</template>
                    <template #content>
                        <div class="space-y-4">
                            <div class="rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ trans('pages.dashboard.core_administration') }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ trans('pages.dashboard.seeded_permissions', { count: permissionGroups.core }) }}</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ trans('pages.dashboard.sso_domain') }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ trans('pages.dashboard.seeded_permissions', { count: permissionGroups.sso }) }}</div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
