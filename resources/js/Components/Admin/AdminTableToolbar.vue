<script setup>
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";
import InputText from "primevue/inputtext";
import Button from "primevue/button";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    title: {
        type: String,
        default: "",
    },
    description: {
        type: String,
        default: "",
    },
    searchable: {
        type: Boolean,
        default: false,
    },
    searchValue: {
        type: String,
        default: "",
    },
    searchPlaceholder: {
        type: String,
        default: "",
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    createLabel: {
        type: String,
        default: "",
    },
    canBulkDelete: {
        type: Boolean,
        default: false,
    },
    bulkDeleteLabel: {
        type: String,
        default: "",
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
    searchContainerClass: {
        type: String,
        default: "w-full sm:max-w-sm",
    },
    titleClass: {
        type: String,
        default: "text-base font-semibold text-slate-950",
    },
    descriptionClass: {
        type: String,
        default: "text-sm text-slate-500",
    },
    actionsClass: {
        type: String,
        default: "",
    },
});

defineEmits(["create", "bulk-delete", "refresh", "submit-search", "update:searchValue"]);

const bulkStatusText = computed(() => {
    if (!props.canBulkDelete) {
        return "";
    }

    if (props.selectedCount > 0) {
        return props.selectableCount > 0
            ? trans("toolbar.bulk.selected_of_total", {
                  count: props.selectedCount,
                  total: props.selectableCount,
              })
            : trans("toolbar.bulk.selected", { count: props.selectedCount });
    }

    if (props.selectableCount === 0) {
        return trans("toolbar.bulk.none");
    }

    return trans("toolbar.bulk.prompt");
});

const resolvedSearchPlaceholder = computed(
    () => props.searchPlaceholder || trans("toolbar.search_placeholder")
);
const resolvedCreateLabel = computed(() => props.createLabel || trans("actions.create"));
const resolvedBulkDeleteLabel = computed(
    () => props.bulkDeleteLabel || trans("toolbar.bulk.delete")
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1 space-y-3">
                <div v-if="title || description" class="space-y-1">
                    <h3 v-if="title" :class="titleClass">
                        {{ title }}
                    </h3>
                    <p v-if="description" :class="descriptionClass">
                        {{ description }}
                    </p>
                </div>

                <div
                    v-if="$slots.search || searchable"
                    :class="searchContainerClass"
                    class="min-w-0"
                >
                    <slot name="search">
                        <IconField class="w-full">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                :modelValue="searchValue"
                                :placeholder="resolvedSearchPlaceholder"
                                class="h-11 w-full"
                                @update:modelValue="$emit('update:searchValue', $event)"
                                @keyup.enter="$emit('submit-search')"
                            />
                        </IconField>
                    </slot>
                </div>

                <div
                    v-if="$slots.filters"
                    class="flex flex-col gap-3 lg:flex-row lg:flex-wrap"
                >
                    <slot name="filters" />
                </div>
            </div>

            <div
                :class="actionsClass"
                class="flex flex-wrap items-center justify-end gap-3 xl:flex-none"
            >
                <span v-if="bulkStatusText" class="text-sm text-slate-500">
                    {{ bulkStatusText }}
                </span>

                <slot name="bulk" />
                <slot name="actions" />
                <slot name="primary" />

                <!-- Refresh -->
                <Button
                    :label="trans('common.refresh')"
                    icon="pi pi-refresh"
                    severity="secondary"
                    outlined
                    :loading="busy"
                    :disabled="busy"
                    data-toolbar-action="refresh"
                    @click="$emit('refresh')"
                />

                <!-- Bulk Delete -->
                <Button
                    v-if="canBulkDelete"
                    :label="resolvedBulkDeleteLabel"
                    icon="pi pi-trash"
                    severity="danger"
                    outlined
                    :disabled="busy || selectedCount === 0"
                    data-toolbar-action="bulk-delete"
                    @click="$emit('bulk-delete')"
                />

                <!-- Create -->
                <Button
                    v-if="canCreate"
                    :label="resolvedCreateLabel"
                    icon="pi pi-plus"
                    severity="info"
                    :disabled="busy"
                    data-toolbar-action="create"
                    @click="$emit('create')"
                />
            </div>
        </div>
    </div>
</template>
