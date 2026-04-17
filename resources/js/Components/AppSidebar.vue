<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import { computed } from "vue";

defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const currentUrl = computed(() => page.url);

const isActive = (item) => currentUrl.value.startsWith(route(item.route));
</script>

<template>
    <aside
        class="flex h-full flex-col border-r border-white/5 bg-slate-950/95 px-5 py-6 text-white"
    >
        <div class="mb-8 flex items-center gap-3 rounded-3xl bg-white/10 px-4 py-4">
            <div
                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-400/20 text-xl text-sky-200"
            >
                <i class="pi pi-shield"></i>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.32em] text-sky-200/70">
                    Sakai-style
                </p>
                <h1 class="text-lg font-semibold">SSO Server</h1>
            </div>
        </div>

        <div class="mb-4 px-3 text-xs uppercase tracking-[0.28em] text-slate-400">
            Administration
        </div>

        <nav class="flex-1 space-y-2">
            <Link
                v-for="item in items"
                :key="item.key"
                :href="route(item.route)"
                class="sidebar-link"
                :class="{ 'sidebar-link-active': isActive(item) }"
            >
                <i :class="[item.icon, 'text-base']"></i>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold">{{ item.label }}</div>
                    <div class="truncate text-xs text-slate-400">
                        {{ item.description }}
                    </div>
                </div>
            </Link>
        </nav>

        <div
            class="surface-card-muted mt-6 border-white/10 bg-white/5 p-4 text-sm text-slate-300"
        >
            <div class="mb-1 font-semibold text-white">
                {{ trans("common.foundation_ready_title") }}
            </div>
            <p class="text-xs leading-6 text-slate-400">
                {{ trans("common.foundation_ready_description") }}
            </p>
        </div>
    </aside>
</template>
