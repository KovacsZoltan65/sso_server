<script setup>
import GroupedCheckboxSelector from '@/Components/Admin/GroupedCheckboxSelector.vue';
import InputText from 'primevue/inputtext';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    permissionOptions: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <label for="role-name" class="text-sm font-medium text-slate-700">Name</label>
            <InputText
                id="role-name"
                v-model="form.name"
                autocomplete="off"
                :disabled="disabled"
                fluid
            />
            <small v-if="form.errors.name" class="text-red-500">
                {{ form.errors.name }}
            </small>
        </div>

        <div v-if="permissionOptions.length" class="grid gap-2">
            <GroupedCheckboxSelector
                v-model="form.permissions"
                :options="permissionOptions"
                fieldLabel="Permissions"
                searchPlaceholder="Search permissions by resource or action"
                emptyMessage="No permissions match the current search."
                groupCountLabel="permissions"
                :disabled="disabled"
            />
            <small v-if="form.errors.permissions" class="text-red-500">
                {{ form.errors.permissions }}
            </small>
            <small v-if="form.errors['permissions.0']" class="text-red-500">
                {{ form.errors['permissions.0'] }}
            </small>
        </div>
    </div>
</template>
