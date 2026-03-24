<script setup>
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Password from 'primevue/password';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    showPasswordFields: {
        type: Boolean,
        default: false,
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
            <label for="user-name" class="text-sm font-medium text-slate-700">Name</label>
            <InputText
                id="user-name"
                v-model="form.name"
                autocomplete="name"
                :disabled="disabled"
                fluid
            />
            <small v-if="form.errors.name" class="text-red-500">
                {{ form.errors.name }}
            </small>
        </div>

        <div class="grid gap-2">
            <label for="user-email" class="text-sm font-medium text-slate-700">Email</label>
            <InputText
                id="user-email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                :disabled="disabled"
                fluid
            />
            <small v-if="form.errors.email" class="text-red-500">
                {{ form.errors.email }}
            </small>
        </div>

        <div class="grid gap-2">
            <label for="user-roles" class="text-sm font-medium text-slate-700">Roles</label>
            <MultiSelect
                id="user-roles"
                v-model="form.roles"
                :options="roleOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Select roles"
                display="chip"
                :disabled="disabled"
                fluid
            />
            <small v-if="form.errors.roles" class="text-red-500">
                {{ form.errors.roles }}
            </small>
            <small v-if="form.errors['roles.0']" class="text-red-500">
                {{ form.errors['roles.0'] }}
            </small>
        </div>

        <template v-if="showPasswordFields">
            <div class="grid gap-2">
                <label for="user-password" class="text-sm font-medium text-slate-700">Password</label>
                <Password
                    id="user-password"
                    v-model="form.password"
                    toggleMask
                    :feedback="false"
                    autocomplete="new-password"
                    :disabled="disabled"
                    fluid
                />
                <small v-if="form.errors.password" class="text-red-500">
                    {{ form.errors.password }}
                </small>
            </div>

            <div class="grid gap-2">
                <label for="user-password-confirmation" class="text-sm font-medium text-slate-700">
                    Confirm password
                </label>
                <Password
                    id="user-password-confirmation"
                    v-model="form.password_confirmation"
                    toggleMask
                    :feedback="false"
                    autocomplete="new-password"
                    :disabled="disabled"
                    fluid
                />
                <small v-if="form.errors.password_confirmation" class="text-red-500">
                    {{ form.errors.password_confirmation }}
                </small>
            </div>
        </template>
    </div>
</template>
