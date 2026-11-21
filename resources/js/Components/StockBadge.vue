<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: String, required: true } });

const map = {
    aman: {
        label: 'Stok Aman',
        badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
        dot: 'bg-emerald-500',
        ping: false,
    },
    menipis: {
        label: 'Stok Menipis',
        badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
        dot: 'bg-amber-500',
        ping: true,
    },
    habis: {
        label: 'Stok Habis',
        badge: 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
        dot: 'bg-rose-500',
        ping: true,
    },
};
const cfg = computed(() => map[props.status] ?? map.aman);
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
            cfg.badge,
        ]"
    >
        <span class="relative flex h-2 w-2">
            <span
                v-if="cfg.ping"
                :class="['absolute inline-flex h-full w-full animate-ping rounded-full opacity-75', cfg.dot]"
            />
            <span :class="['relative inline-flex h-2 w-2 rounded-full', cfg.dot]" />
        </span>
        {{ cfg.label }}
    </span>
</template>
