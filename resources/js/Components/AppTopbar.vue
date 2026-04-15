<script setup>
import { router } from "@inertiajs/vue3";

import Avatar from "primevue/avatar";
import Button from "primevue/button";
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";

defineProps({
    user: {
        type: Object,
        default: null,
    },
    navigationOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["logout", "toggle-navigation"]);

const goToProfile = () => {
    router.get(route("profile.edit"));
};
</script>

<template>
    <div class="surface-card mb-6 flex items-center justify-between gap-4 px-5 py-4">
        <div class="flex items-center gap-3">
            <!-- Hamburger Menu -->
            <Button
                class="lg:hidden"
                icon="pi pi-bars"
                severity="contrast"
                rounded
                text
                :aria-label="$t('topbar.open_navigation')"
                aria-controls="app-mobile-navigation"
                :aria-expanded="String(navigationOpen)"
                @click="emit('toggle-navigation')"
            />
            <div>
                <div class="eyebrow">{{ $t("topbar.server.eyebrow") }}</div>
                <div class="text-lg font-semibold">
                    {{ $t("topbar.server.title") }}
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <LanguageSwitcher />
            <div class="hidden text-right sm:block">
                <div class="text-sm font-semibold">{{ user?.name }}</div>
                <div class="text-xs text-slate-500">{{ user?.email }}</div>
            </div>
            <Avatar
                :label="user?.name?.charAt(0) ?? 'U'"
                shape="circle"
                class="bg-sky-100 text-sky-700"
            />
            <Button
                icon="pi pi-user"
                severity="secondary"
                text
                rounded
                :aria-label="$t('common.profile')"
                @click="goToProfile"
            />
            <Button
                icon="pi pi-sign-out"
                severity="secondary"
                text
                rounded
                :aria-label="$t('common.logout')"
                @click="emit('logout')"
            />
        </div>
    </div>
</template>
