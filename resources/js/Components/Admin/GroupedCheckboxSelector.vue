<script setup>
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import { trans } from "laravel-vue-i18n";
import { computed, ref } from "vue";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    fieldLabel: { type: String, default: () => trans("fields.options") },
    searchPlaceholder: {
        type: String,
        default: () => trans("table.search_group_or_action"),
    },
    emptyMessage: { type: String, default: () => trans("table.empty_filtered") },
    groupCountLabel: { type: String, default: () => trans("table.items") },
    allowInternalScroll: {
        type: Boolean,
        default: true,
    },
    denseGrid: {
        type: Boolean,
        default: false,
    },
    twoColumnGrid: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

const searchTerm = ref("");

const actionOrder = [
    "viewAny",
    "view",
    "create",
    "update",
    "delete",
    "deleteAny",
    "restore",
    "restoreAny",
    "forceDelete",
    "forceDeleteAny",
];

const prettifyResource = (resource) =>
    resource
        .split("-")
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(" ");

const prettifyAction = (action) =>
    action
        .replace(/([a-z])([A-Z])/g, "$1 $2")
        .replace(/^./, (character) => character.toUpperCase());

const groupedOptions = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();
    const groups = new Map();

    props.options.forEach((option) => {
        const optionValue = option.value ?? option.label ?? "";
        const optionLabel = option.label ?? optionValue;
        const [parsedResource = "misc", parsedAction = optionValue] = String(
            optionValue
        ).split(".");
        const resource = option.groupKey ?? option.group ?? parsedResource;
        const groupLabel = option.groupLabel ?? prettifyResource(String(resource));
        const action = option.action ?? parsedAction;
        const itemLabel =
            option.itemLabel ??
            prettifyAction(String(action === optionValue ? optionLabel : action));
        const searchableText = `${resource} ${groupLabel} ${action} ${optionLabel}`.toLowerCase();

        if (query && !searchableText.includes(query)) {
            return;
        }

        if (!groups.has(resource)) {
            groups.set(resource, {
                key: String(resource),
                label: groupLabel,
                items: [],
            });
        }

        groups.get(resource).items.push({
            label: itemLabel,
            value: optionValue,
            action: String(action),
            resource: String(resource),
            helper: option.helper ?? optionLabel,
        });
    });

    return Array.from(groups.values())
        .map((group) => ({
            ...group,
            items: group.items.sort((left, right) => {
                const leftIndex = actionOrder.indexOf(left.action);
                const rightIndex = actionOrder.indexOf(right.action);

                if (leftIndex !== -1 || rightIndex !== -1) {
                    if (leftIndex === -1) {
                        return 1;
                    }

                    if (rightIndex === -1) {
                        return -1;
                    }

                    if (leftIndex !== rightIndex) {
                        return leftIndex - rightIndex;
                    }
                }

                return left.action.localeCompare(right.action);
            }),
        }))
        .sort((left, right) => left.label.localeCompare(right.label));
});

const selectedValues = computed(() => props.modelValue ?? []);

const isChecked = (item) => selectedValues.value.includes(item.value);

const updateSelection = (values) => {
    emit("update:modelValue", [...new Set(values)]);
};

const toggleItem = (item, checked) => {
    if (checked) {
        updateSelection([...selectedValues.value, item.value]);
        return;
    }

    updateSelection(selectedValues.value.filter((value) => value !== item.value));
};

const selectGroup = (group) => {
    updateSelection([...selectedValues.value, ...group.items.map((item) => item.value)]);
};

const clearGroup = (group) => {
    const groupValues = new Set(group.items.map((item) => item.value));

    updateSelection(selectedValues.value.filter((value) => !groupValues.has(value)));
};
</script>

<template>
    <div class="grid min-w-0 gap-4 overflow-x-hidden">
        <div class="grid gap-2">
            <label
                for="grouped-checkbox-search"
                class="text-sm font-medium text-slate-700"
                >{{ fieldLabel }}</label
            >
            <InputText
                id="grouped-checkbox-search"
                v-model="searchTerm"
                type="search"
                :placeholder="searchPlaceholder"
                :disabled="disabled"
                fluid
            />
        </div>

        <div
            :class="[
                'grid min-w-0 gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 overflow-x-hidden',
                allowInternalScroll
                    ? 'max-h-[28rem] overflow-y-auto'
                    : 'overflow-y-visible',
            ]"
        >
            <div
                v-for="group in groupedOptions"
                :key="group.key"
                class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ group.label }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ group.items.length }} {{ groupCountLabel }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <!-- Select All -->
                        <Button
                            type="button"
                            :label="trans('actions.select_all')"
                            size="small"
                            severity="secondary"
                            text
                            :disabled="disabled"
                            @click="selectGroup(group)"
                        />

                        <!-- Clear Selection -->
                        <Button
                            type="button"
                            :label="trans('actions.clear_selection')"
                            size="small"
                            severity="secondary"
                            text
                            :disabled="disabled"
                            @click="clearGroup(group)"
                        />
                    </div>
                </div>

                <div
                    :class="[
                        'mt-4 grid min-w-0 gap-3',
                        denseGrid
                            ? 'grid-cols-1 2xl:grid-cols-2'
                            : twoColumnGrid
                            ? 'grid-cols-1 lg:grid-cols-2'
                            : 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3',
                    ]"
                >
                    <label
                        v-for="item in group.items"
                        :key="item.value"
                        class="flex min-w-0 items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-3"
                    >
                        <Checkbox
                            :binary="true"
                            :modelValue="isChecked(item)"
                            :disabled="disabled"
                            @update:modelValue="toggleItem(item, $event)"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="break-words text-sm font-medium text-slate-800">
                                {{ item.label }}
                            </div>
                            <div class="break-words text-xs leading-5 text-slate-500">
                                {{ item.helper }}
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div
                v-if="groupedOptions.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500"
            >
                {{ emptyMessage }}
            </div>
        </div>
    </div>
</template>
