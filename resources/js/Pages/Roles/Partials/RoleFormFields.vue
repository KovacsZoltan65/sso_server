<script setup>
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    guardName: {
        type: String,
        default: 'web',
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

        <div class="grid gap-2">
            <label for="role-guard" class="text-sm font-medium text-slate-700">Guard</label>
            <InputText
                id="role-guard"
                :modelValue="guardName"
                disabled
                fluid
            />
        </div>

        <div v-if="permissionOptions.length" class="grid gap-2">
            <label for="role-permissions" class="text-sm font-medium text-slate-700">Permissions</label>
            <MultiSelect
                id="role-permissions"
                v-model="form.permissions"
                :options="permissionOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Select permissions"
                display="chip"
                filter
                :disabled="disabled"
                fluid
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
