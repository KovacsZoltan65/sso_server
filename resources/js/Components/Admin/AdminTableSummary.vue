<script setup>
import { computed } from 'vue';

const props = defineProps({
    page: {
        type: Number,
        default: 1,
    },
    perPage: {
        type: Number,
        default: 10,
    },
    total: {
        type: Number,
        default: 0,
    },
    from: {
        type: Number,
        default: null,
    },
    to: {
        type: Number,
        default: null,
    },
    currentPage: {
        type: Number,
        default: null,
    },
    lastPage: {
        type: Number,
        default: null,
    },
    itemLabel: {
        type: String,
        default: 'records',
    },
    emptyLabel: {
        type: String,
        default: 'No records to display.',
    },
});

const normalizedTotal = computed(() => {
    const value = Number(props.total);

    return Number.isFinite(value) && value >= 0 ? value : 0;
});

const normalizedFrom = computed(() => {
    if (normalizedTotal.value === 0) {
        return 0;
    }

    if (Number.isFinite(Number(props.from))) {
        return Number(props.from);
    }

    return Math.min(normalizedTotal.value, ((props.page || 1) - 1) * (props.perPage || 10) + 1);
});

const normalizedTo = computed(() => {
    if (normalizedTotal.value === 0) {
        return 0;
    }

    if (Number.isFinite(Number(props.to))) {
        return Number(props.to);
    }

    return Math.min(normalizedTotal.value, normalizedFrom.value + (props.perPage || 10) - 1);
});

const normalizedCurrentPage = computed(() => {
    if (Number.isFinite(Number(props.currentPage)) && Number(props.currentPage) > 0) {
        return Number(props.currentPage);
    }

    return Math.max(1, Number(props.page) || 1);
});

const normalizedLastPage = computed(() => {
    if (Number.isFinite(Number(props.lastPage)) && Number(props.lastPage) > 0) {
        return Number(props.lastPage);
    }

    if (normalizedTotal.value === 0 || (props.perPage || 0) <= 0) {
        return 1;
    }

    return Math.max(1, Math.ceil(normalizedTotal.value / props.perPage));
});
</script>

<template>
    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <slot
            name="default"
            :from="normalizedFrom"
            :to="normalizedTo"
            :total="normalizedTotal"
            :current-page="normalizedCurrentPage"
            :last-page="normalizedLastPage"
        >
            <div v-if="normalizedTotal > 0">
                Showing {{ normalizedFrom }}-{{ normalizedTo }} of {{ normalizedTotal }} {{ itemLabel }}
            </div>
            <div v-else>
                {{ emptyLabel }}
            </div>
            <div>
                Page {{ normalizedCurrentPage }} / {{ normalizedLastPage }}
            </div>
        </slot>
    </div>
</template>
