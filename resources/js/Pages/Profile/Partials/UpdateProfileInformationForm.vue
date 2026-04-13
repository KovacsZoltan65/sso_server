<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const toast = useToast();

const form = useForm({
    name: user.name,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Profil mentve',
                detail: 'A profiladataid sikeresen frissultek.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Sikertelen mentes',
                detail: 'Ellenorizd a megadott adatokat, majd probald ujra.',
                life: 4000,
            });
        },
    });
};

const notifyVerificationSent = () => {
    toast.add({
        severity: 'success',
        summary: 'Ellenorzo email elkuldve',
        detail: 'Uj ellenorzo linket kuldtunk az email-cimedre.',
        life: 3000,
    });
};
</script>

<template>
    <section class="space-y-5">
        <div class="grid gap-4">
            <div class="grid gap-2">
                <label for="profile-name" class="text-sm font-medium text-slate-700">Nev</label>
                <InputText
                    id="profile-name"
                    v-model="form.name"
                    autocomplete="name"
                    autofocus
                    class="w-full"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <label for="profile-email" class="text-sm font-medium text-slate-700">Email</label>
                <InputText
                    id="profile-email"
                    :model-value="user.email"
                    autocomplete="username"
                    class="w-full"
                    disabled
                    readonly
                />
                <p class="text-sm leading-6 text-slate-500">
                    Az email cim itt csak olvashato, hogy a kozos SSO identitas-terkep stabil maradjon.
                </p>
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4"
            >
                <div class="text-sm font-semibold text-amber-950">Email megerosites szukseges</div>
                <p class="mt-1 text-sm leading-6 text-amber-900">
                    Az email-cimed meg nincs megerositve.
                </p>
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="mt-3 inline-flex items-center rounded-xl border border-amber-300 px-3 py-2 text-sm font-medium text-amber-950 transition hover:bg-amber-100"
                    @click="notifyVerificationSent"
                >
                    Uj ellenorzo email kuldese
                </Link>

                <p
                    v-if="status === 'verification-link-sent'"
                    class="mt-3 text-sm font-medium text-emerald-700"
                >
                    Uj ellenorzo linket kuldtunk az email-cimedre.
                </p>
            </div>
        </div>

        <div class="flex justify-end">
            <Button
                label="Mentes"
                icon="pi pi-save"
                :loading="form.processing"
                :disabled="form.processing"
                @click="submit"
            />
        </div>
    </section>
</template>
