<script setup>
import { router, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import { computed, ref } from "vue";

const page = usePage();
const isSubmitting = ref(false);

const availableLocales = computed(() => page.props.locale?.available ?? ["hu", "en"]);
const currentLocale = computed(() => page.props.locale?.current ?? "hu");

const localeLabels = {
    hu: "HU",
    en: "EN",
};

const switchLocale = (locale) => {
    if (isSubmitting.value || locale === currentLocale.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        route("locale.update"),
        { locale },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            onFinish: () => {
                isSubmitting.value = false;
                window.location.reload();
            },
        }
    );
};
</script>

<template>
    <div
        class="flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 p-1"
    >
        <Button
            v-for="locale in availableLocales"
            :key="locale"
            :label="localeLabels[locale] ?? locale.toUpperCase()"
            size="small"
            rounded
            :severity="locale === currentLocale ? 'contrast' : 'secondary'"
            :text="locale !== currentLocale"
            :disabled="isSubmitting"
            @click="switchLocale(locale)"
        />
    </div>
</template>
