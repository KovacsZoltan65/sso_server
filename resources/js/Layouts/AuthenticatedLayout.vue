<script setup>
import AppSidebar from "@/Components/AppSidebar.vue";
import AppTopbar from "@/Components/AppTopbar.vue";
import { useNavigation } from "@/Composables/useNavigation";
import { router, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import { onMounted, onUnmounted, ref, watch } from "vue";

const showMobileNav = ref(false);
const page = usePage();
const { items, user } = useNavigation();

const logout = () => {
    router.post(route("logout"));
};

const closeMobileNav = () => {
    showMobileNav.value = false;
};

const toggleMobileNav = () => {
    showMobileNav.value = !showMobileNav.value;
};

const handleEscape = (event) => {
    if (event.key === "Escape" && showMobileNav.value) {
        closeMobileNav();
    }
};

watch(
    () => page.url,
    () => {
        closeMobileNav();
    }
);

onMounted(() => {
    window.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscape);
});
</script>

<template>
    <div class="shell-grid">
        <div class="hidden lg:block">
            <AppSidebar :items="items" />
        </div>

        <div
            class="relative flex min-h-0 h-screen flex-col overflow-hidden bg-transparent px-4 py-4 sm:px-6 lg:px-8"
        >
            <div
                v-if="showMobileNav"
                class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"
                @click="closeMobileNav"
            />

            <div
                v-if="showMobileNav"
                id="app-mobile-navigation"
                class="fixed inset-y-0 left-0 z-50 w-[18rem] border-r border-white/20 bg-slate-950 px-4 py-6 text-white lg:hidden"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div
                        class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300"
                    >
                        {{ trans("common.navigation") }}
                    </div>
                    <Button
                        icon="pi pi-times"
                        severity="secondary"
                        text
                        rounded
                        :aria-label="trans('navigation.close_navigation.aria_label')"
                        @click="closeMobileNav"
                    />
                </div>
                <AppSidebar :items="items" />
            </div>

            <AppTopbar
                class="flex-none"
                :user="user"
                :navigation-open="showMobileNav"
                @logout="logout"
                @toggle-navigation="toggleMobileNav"
            />

            <main class="flex min-h-0 flex-1 flex-col">
                <slot />
            </main>
        </div>
    </div>
</template>
