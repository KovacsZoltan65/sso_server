<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import Password from 'primevue/password';
import Button from 'primevue/button';
import { trans } from 'laravel-vue-i18n';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const toast = useToast();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.add({
                severity: 'success',
                summary: trans('profile.password_updated_summary'),
                detail: trans('profile.password_updated_detail'),
                life: 3000,
            });
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.$el?.querySelector('input')?.focus?.();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.$el?.querySelector('input')?.focus?.();
            }

            toast.add({
                severity: 'error',
                summary: trans('profile.password_update_failed_summary'),
                detail: trans('profile.password_update_failed_detail'),
                life: 4000,
            });
        },
    });
};
</script>

<template>
    <section class="space-y-5">
        <div class="grid gap-4">
            <div class="grid gap-2">
                <label for="current_password" class="text-sm font-medium text-slate-700">{{ trans('profile.current_password') }}</label>
                <Password
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    autocomplete="current-password"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    fluid
                />
                <InputError :message="form.errors.current_password" />
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium text-slate-700">{{ trans('profile.new_password') }}</label>
                <Password
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    autocomplete="new-password"
                    class="w-full"
                    input-class="w-full"
                    toggle-mask
                    fluid
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-medium text-slate-700">{{ trans('profile.confirm_password') }}</label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    autocomplete="new-password"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    fluid
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>
        </div>

        <div class="flex justify-end">
            <Button
                :label="trans('common.save')"
                icon="pi pi-save"
                :loading="form.processing"
                :disabled="form.processing"
                @click="updatePassword"
            />
        </div>
    </section>
</template>
