<script setup>
import Button from "primevue/button";

defineProps({
    canCreate: {
        type: Boolean,
        default: false,
    },
    createLabel: {
        type: String,
        default: "Create",
    },
    canBulkDelete: {
        type: Boolean,
        default: false,
    },
    bulkDeleteLabel: {
        type: String,
        default: "Delete Selected",
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
    selectableCount: {
        type: Number,
        default: 0,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["create", "bulk-delete", "refresh"]);
</script>

<template>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="w-full sm:max-w-sm">
            <slot name="search" />
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <span v-if="selectedCount" class="text-sm text-slate-500">
                {{ selectedCount }} selected
            </span>
            <span
                v-else-if="canBulkDelete && selectableCount === 0"
                class="text-sm text-slate-500"
            >
                No deletable records on this page
            </span>

            <!-- Refresh -->
            <Button
                label="Refresh"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                :loading="busy"
                :disabled="busy"
                @click="$emit('refresh')"
            />

            <!-- Bulk Delete -->
            <Button
                v-if="canBulkDelete"
                :label="bulkDeleteLabel"
                icon="pi pi-trash"
                severity="danger"
                outlined
                :disabled="busy || selectedCount === 0"
                @click="$emit('bulk-delete')"
            />

            <!-- Create -->
            <Button
                v-if="canCreate"
                :label="createLabel"
                icon="pi pi-plus"
                severity="info"
                :disabled="busy"
                @click="$emit('create')"
            />
        </div>
    </div>
</template>
