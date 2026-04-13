<script setup>
import AdminFormCard from '@/Components/Admin/AdminFormCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import Toast from 'primevue/toast';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <Toast />

        <div class="admin-form-page">
            <PageHeader
                title="Profil"
                description="Fiokadataid es biztonsagi beallitasaid kezelese a kozos admin felulet ritmusa szerint."
            />

            <div class="admin-form-shell space-y-6 overflow-y-auto pr-1">
                <div class="grid gap-6 lg:grid-cols-2">
                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Profil adatok</div>
                                <p class="text-sm text-slate-500">
                                    Frissitsd a megjelenitett nevet, mikozben a kozos SSO email-azonosito valtozatlan marad.
                                </p>
                            </div>
                        </template>

                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </AdminFormCard>

                    <AdminFormCard>
                        <template #header>
                            <div class="space-y-1">
                                <div class="text-sm font-semibold text-slate-900">Jelszo modositasa</div>
                                <p class="text-sm text-slate-500">
                                    Csereld le a jelszavadat egy uj, eros jelszora ugyanabban a self-service feluletben.
                                </p>
                            </div>
                        </template>

                        <UpdatePasswordForm />
                    </AdminFormCard>
                </div>

                <AdminFormCard :grow="false">
                    <template #header>
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">Fiok torlese</div>
                            <p class="text-sm text-slate-500">
                                Ez a muvelet vegleges. A torles elott ellenorizd, hogy minden szukseges adatot elmentettel.
                            </p>
                        </div>
                    </template>

                    <DeleteUserForm />
                </AdminFormCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
