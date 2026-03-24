<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { reactive, watch } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    pagination: {
        type: Object,
        required: true,
    },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    perPage: props.filters.perPage ?? 10,
});

watch(
    () => ({ ...filterState }),
    (filters) => {
        router.get(route('admin.users.index'), filters, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
    { deep: true },
);

const perPageOptions = [5, 10, 15, 25];
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <PageHeader
            title="Users"
            description="Example read flow using Controller -> Service -> Repository -> Data. This page is intentionally simple but production-oriented for future user and operator modules."
        />

        <Card class="surface-card">
            <template #content>
                <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                        <span class="p-input-icon-left w-full sm:max-w-sm">
                            <i class="pi pi-search text-slate-400"></i>
                            <InputText v-model="filterState.search" placeholder="Search name or email" class="w-full" />
                        </span>
                        <Select v-model="filterState.perPage" :options="perPageOptions" class="w-full sm:w-36" />
                    </div>

                    <Button
                        label="Repository-backed read model"
                        icon="pi pi-database"
                        severity="secondary"
                        outlined
                    />
                </div>

                <DataTable :value="rows" data-key="id" striped-rows responsive-layout="scroll">
                    <Column field="name" header="Name" />
                    <Column field="email" header="Email" />
                    <Column header="Roles">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="role in data.roles"
                                    :key="role"
                                    :value="role"
                                    severity="info"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="Verified">
                        <template #body="{ data }">
                            <Tag :value="data.emailVerifiedAt ? 'Verified' : 'Pending'" :severity="data.emailVerifiedAt ? 'success' : 'warn'" />
                        </template>
                    </Column>
                    <Column field="createdAt" header="Created At" />
                </DataTable>

                <div class="mt-5 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} users
                    </div>
                    <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
