<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { trans } from 'laravel-vue-i18n';
import Password from 'primevue/password';
import { useToast } from 'primevue/usetoast';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const toast = useToast();

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => {
        passwordInput.value?.$el?.querySelector('input')?.focus?.();
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: trans('profile.delete_started_summary'),
                detail: trans('profile.delete_started_detail'),
                life: 3000,
            });
            closeModal();
        },
        onError: () => {
            passwordInput.value?.$el?.querySelector('input')?.focus?.();
            toast.add({
                severity: 'error',
                summary: trans('profile.delete_failed_summary'),
                detail: trans('profile.delete_failed_detail'),
                life: 4000,
            });
        },
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <section class="space-y-5">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4">
            <div class="text-sm font-semibold text-rose-950">{{ trans('profile.delete_permanent_title') }}</div>
            <p class="mt-1 text-sm leading-6 text-rose-900">
                {{ trans('profile.delete_permanent_description') }}
            </p>
        </div>

        <div class="flex justify-end">
            <Button
                :label="trans('profile.delete_title')"
                icon="pi pi-trash"
                severity="danger"
                @click="confirmUserDeletion"
            />
        </div>

        <Dialog
            v-model:visible="confirmingUserDeletion"
            modal
            :header="trans('profile.delete_confirm_title')"
            :style="{ width: '32rem' }"
            @hide="closeModal"
        >
            <div class="space-y-4">
                <p class="text-sm leading-6 text-slate-600">
                    {{ trans('profile.delete_confirm_description') }}
                </p>

                <div class="grid gap-2">
                    <label for="delete-account-password" class="text-sm font-medium text-slate-700">{{ trans('fields.password') }}</label>
                    <Password
                        id="delete-account-password"
                        ref="passwordInput"
                        v-model="form.password"
                        class="w-full"
                        input-class="w-full"
                        :feedback="false"
                        toggle-mask
                        fluid
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        :label="trans('common.cancel')"
                        severity="secondary"
                        outlined
                        @click="closeModal"
                    />
                    <Button
                        type="button"
                        :label="trans('profile.delete_confirm_action')"
                        icon="pi pi-trash"
                        severity="danger"
                        :loading="form.processing"
                        :disabled="form.processing"
                        @click="deleteUser"
                    />
                </div>
            </template>
        </Dialog>
    </section>
</template>
