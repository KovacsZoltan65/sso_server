<script setup>
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        default: 'create',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <label for="scope-name" class="text-sm font-medium text-slate-700">Name</label>
                    <InputText
                        id="scope-name"
                        v-model="form.name"
                        autocomplete="off"
                        :disabled="loading"
                        fluid
                    />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="grid gap-2">
                    <label for="scope-code" class="text-sm font-medium text-slate-700">Code</label>
                    <InputText
                        id="scope-code"
                        v-model="form.code"
                        autocomplete="off"
                        :disabled="loading"
                        placeholder="users.read"
                        fluid
                    />
                    <small class="text-slate-500">
                        Use lowercase codes with dots, hyphens, or underscores. Example: `clients.manage`
                    </small>
                    <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                </div>

                <div class="grid gap-2">
                    <label for="scope-description" class="text-sm font-medium text-slate-700">Description</label>
                    <Textarea
                        id="scope-description"
                        v-model="form.description"
                        autoResize
                        rows="6"
                        :disabled="loading"
                        fluid
                    />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>
            </div>

            <div class="grid gap-4 self-start rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <Checkbox
                            inputId="scope-is-active"
                            :binary="true"
                            :modelValue="form.is_active"
                            :disabled="loading"
                            @update:modelValue="form.is_active = $event"
                        />
                        <label for="scope-is-active" class="text-sm text-slate-700">
                            Scope is active
                        </label>
                    </div>
                    <small v-if="form.errors.is_active" class="text-red-500">{{ form.errors.is_active }}</small>
                </div>

                <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <div class="font-medium">Usage note</div>
                    <p class="mt-1 leading-6 text-sky-800">
                        Scope codes are technical identifiers. Renaming a code that is already assigned to clients is blocked.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
