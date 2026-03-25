<script setup>
import AppSidebar from '@/Components/AppSidebar.vue';
import AppTopbar from '@/Components/AppTopbar.vue';
import { useNavigation } from '@/Composables/useNavigation';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { ref } from 'vue';

const showMobileNav = ref(false);
const { items, user } = useNavigation();

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="shell-grid">
        <div class="hidden lg:block">
            <AppSidebar :items="items" />
        </div>

        <div class="relative flex min-h-screen flex-col bg-transparent px-4 py-4 sm:px-6 lg:px-8">
            <div
                v-if="showMobileNav"
                class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"
                @click="showMobileNav = false"
            />

            <div
                v-if="showMobileNav"
                class="fixed inset-y-0 left-0 z-50 w-[18rem] border-r border-white/20 bg-slate-950 px-4 py-6 text-white lg:hidden"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">Navigation</div>
                    <Button icon="pi pi-times" severity="secondary" text rounded @click="showMobileNav = false" />
                </div>
                <AppSidebar :items="items" />
            </div>

            <AppTopbar
                :user="user"
                @logout="logout"
                @toggle-navigation="showMobileNav = !showMobileNav"
            />

            <main class="flex min-h-0 flex-1 flex-col">
                <slot />
            </main>
        </div>
    </div>
</template>
