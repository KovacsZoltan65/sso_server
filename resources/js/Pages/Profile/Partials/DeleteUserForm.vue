<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
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
                summary: 'Fiok torlese elinditva',
                detail: 'A fiok torlese sikeresen megerositesre kerult.',
                life: 3000,
            });
            closeModal();
        },
        onError: () => {
            passwordInput.value?.$el?.querySelector('input')?.focus?.();
            toast.add({
                severity: 'error',
                summary: 'Sikertelen fioktorles',
                detail: 'Add meg ujra a jelszavadat a torles megerositesehez.',
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
            <div class="text-sm font-semibold text-rose-950">Vegleges muvelet</div>
            <p class="mt-1 text-sm leading-6 text-rose-900">
                A fiok torlese utan az osszes kapcsolodo adat helyreallithatatlanul elveszik.
            </p>
        </div>

        <div class="flex justify-end">
            <Button
                label="Fiok torlese"
                icon="pi pi-trash"
                severity="danger"
                @click="confirmUserDeletion"
            />
        </div>

        <Dialog
            v-model:visible="confirmingUserDeletion"
            modal
            header="Fiok torlesenek megerositese"
            :style="{ width: '32rem' }"
            @hide="closeModal"
        >
            <div class="space-y-4">
                <p class="text-sm leading-6 text-slate-600">
                    A vegleges torleshez add meg a jelszavadat. Ez a muvelet nem vonhato vissza.
                </p>

                <div class="grid gap-2">
                    <label for="delete-account-password" class="text-sm font-medium text-slate-700">Jelszo</label>
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
                        label="Megse"
                        severity="secondary"
                        outlined
                        @click="closeModal"
                    />
                    <Button
                        type="button"
                        label="Fiok vegleges torlese"
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
