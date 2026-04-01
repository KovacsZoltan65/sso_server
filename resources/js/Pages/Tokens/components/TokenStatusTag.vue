<script setup>
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const label = computed(() => {
    return ({
        active: 'Active',
        expired: 'Expired',
        revoked: 'Revoked',
        rotated: 'Rotated',
    })[props.status] ?? props.status;
});

const severity = computed(() => {
    return ({
        active: 'success',
        expired: 'warn',
        revoked: 'danger',
        rotated: 'contrast',
    })[props.status] ?? 'secondary';
});
</script>

<template>
    <Tag :value="label" :severity="severity" />
</template>
