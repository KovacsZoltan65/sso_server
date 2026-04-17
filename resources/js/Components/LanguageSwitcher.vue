<script setup>
import { usePage } from "@inertiajs/vue3";
import { currentLocale, loadLanguageAsync } from "laravel-vue-i18n";
import axios from "axios";
import Button from "primevue/button";
import { computed, ref } from "vue";

const page = usePage();
const isSubmitting = ref(false);

const availableLocales = computed(() => page.props.locale?.available ?? ["hu", "en"]);
const activeLocale = computed(() => currentLocale.value || page.props.locale?.current || "hu");

const localeLabels = {
    hu: "HU",
    en: "EN",
};

const switchLocale = async (locale) => {
    if (isSubmitting.value || locale === activeLocale.value) {
        return;
    }

    isSubmitting.value = true;
    const previousLocale = activeLocale.value;

    try {
        await loadLanguageAsync(locale);
        document.documentElement.setAttribute("lang", locale);
    } catch (_error) {
        isSubmitting.value = false;
        return;
    }

    try {
        await axios.post(route("locale.update"), { locale }, {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
    } catch (_error) {
        await loadLanguageAsync(previousLocale);
        document.documentElement.setAttribute("lang", previousLocale);
    } finally {
        isSubmitting.value = false;
    }
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
            :severity="locale === activeLocale ? 'contrast' : 'secondary'"
            :text="locale !== activeLocale"
            :disabled="isSubmitting"
            @click="switchLocale(locale)"
        />
    </div>
</template>
