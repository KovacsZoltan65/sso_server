<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { computed, ref } from 'vue';

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
});

const emit = defineEmits(['update:modelValue']);

const searchTerm = ref('');

const actionOrder = [
    'viewAny',
    'view',
    'create',
    'update',
    'delete',
    'deleteAny',
    'restore',
    'restoreAny',
    'forceDelete',
    'forceDeleteAny',
];

const prettifyResource = (resource) => resource
    .split('-')
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ');

const prettifyAction = (action) => action
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/^./, (character) => character.toUpperCase());

const permissionGroups = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();
    const groups = new Map();

    props.options.forEach((option) => {
        const permissionName = option.value ?? option.label ?? '';
        const [resource = 'misc', action = permissionName] = permissionName.split('.');
        const searchableText = `${resource} ${action} ${option.label ?? permissionName}`.toLowerCase();

        if (query && !searchableText.includes(query)) {
            return;
        }

        if (!groups.has(resource)) {
            groups.set(resource, {
                key: resource,
                label: prettifyResource(resource),
                permissions: [],
            });
        }

        groups.get(resource).permissions.push({
            label: prettifyAction(action),
            value: option.value,
            action,
            resource,
        });
    });

    return Array.from(groups.values())
        .map((group) => ({
            ...group,
            permissions: group.permissions.sort((left, right) => {
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

const isChecked = (permission) => selectedValues.value.includes(permission.value);

const updateSelection = (values) => {
    emit('update:modelValue', [...new Set(values)]);
};

const togglePermission = (permission, checked) => {
    if (checked) {
        updateSelection([...selectedValues.value, permission.value]);
        return;
    }

    updateSelection(selectedValues.value.filter((value) => value !== permission.value));
};

const selectGroup = (group) => {
    updateSelection([
        ...selectedValues.value,
        ...group.permissions.map((permission) => permission.value),
    ]);
};

const clearGroup = (group) => {
    const groupValues = new Set(group.permissions.map((permission) => permission.value));

    updateSelection(selectedValues.value.filter((value) => !groupValues.has(value)));
};
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <label for="role-permission-search" class="text-sm font-medium text-slate-700">Permissions</label>
            <InputText
                id="role-permission-search"
                v-model="searchTerm"
                type="search"
                placeholder="Search permissions by resource or action"
                :disabled="disabled"
                fluid
            />
        </div>

        <div class="grid max-h-[28rem] gap-4 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
            <div
                v-for="group in permissionGroups"
                :key="group.key"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ group.label }}</h3>
                        <p class="text-xs text-slate-500">{{ group.permissions.length }} permissions</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            label="Select all"
                            size="small"
                            severity="secondary"
                            text
                            :disabled="disabled"
                            @click="selectGroup(group)"
                        />
                        <Button
                            type="button"
                            label="Clear"
                            size="small"
                            severity="secondary"
                            text
                            :disabled="disabled"
                            @click="clearGroup(group)"
                        />
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.value"
                        class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-3"
                    >
                        <Checkbox
                            :binary="true"
                            :modelValue="isChecked(permission)"
                            :disabled="disabled"
                            @update:modelValue="togglePermission(permission, $event)"
                        />
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-800">{{ permission.label }}</div>
                            <div class="truncate text-xs text-slate-500">{{ permission.resource }}.{{ permission.action }}</div>
                        </div>
                    </label>
                </div>
            </div>

            <div v-if="permissionGroups.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                No permissions match the current search.
            </div>
        </div>
    </div>
</template>
