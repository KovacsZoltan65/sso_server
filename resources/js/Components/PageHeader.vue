<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="mb-6">
        <div class="eyebrow">Workspace</div>
        <h2 class="section-title mt-2">{{ title }}</h2>
        <p v-if="description" class="section-copy mt-2 max-w-3xl">{{ description }}</p>
    </div>
</template>
