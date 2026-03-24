<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, Link } from '@inertiajs/vue3';
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
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <PageHeader
            title="Dashboard"
            description="Operational entry point for the central SSO server foundation. The shell is wired for auth, permissions, auditing, and future client-management modules."
        />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard v-for="stat in stats" :key="stat.label" :stat="stat" />
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <Card class="surface-card">
                <template #title>Recent Activity</template>
                <template #subtitle>Activitylog is configured and already receives auth/profile events.</template>
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
                        No activity has been written yet. Sign in or update a profile to generate the first audit entries.
                    </div>
                </template>
            </Card>

            <div class="space-y-6">
                <Card class="surface-card">
                    <template #title>Quick Actions</template>
                    <template #content>
                        <div class="space-y-3">
                            <Link :href="route('admin.users.index')" class="block">
                                <Button label="Open users" icon="pi pi-users" class="w-full justify-center" />
                            </Link>
                            <Link :href="route('admin.sso-clients.index')" class="block">
                                <Button
                                    label="Review client placeholder"
                                    icon="pi pi-desktop"
                                    severity="secondary"
                                    class="w-full justify-center"
                                />
                            </Link>
                        </div>
                    </template>
                </Card>

                <Card class="surface-card">
                    <template #title>Permission Layout</template>
                    <template #content>
                        <div class="space-y-4">
                            <div class="rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">Core administration</div>
                                <div class="mt-1 text-sm text-slate-500">{{ permissionGroups.core }} seeded permissions</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">SSO domain</div>
                                <div class="mt-1 text-sm text-slate-500">{{ permissionGroups.sso }} seeded permissions</div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
