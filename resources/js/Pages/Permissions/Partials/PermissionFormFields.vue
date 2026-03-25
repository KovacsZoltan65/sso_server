<script setup>
import InputText from 'primevue/inputtext';

defineProps({
    form: {
        type: Object,
        required: true,
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
            <label for="permission-name" class="text-sm font-medium text-slate-700">Name</label>
            <InputText
                id="permission-name"
                v-model="form.name"
                autocomplete="off"
                :disabled="disabled"
                fluid
            />
            <small v-if="form.errors.name" class="text-red-500">
                {{ form.errors.name }}
            </small>
        </div>
    </div>
</template>
