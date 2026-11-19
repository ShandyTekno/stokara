<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: String, required: true } });

const map = {
    aman: { label: 'Stok Aman', css: 'bg-green-100 text-green-800' },
    menipis: { label: 'Stok Menipis', css: 'bg-yellow-100 text-yellow-800' },
    habis: { label: 'Stok Habis', css: 'bg-red-100 text-red-800' },
};
const cfg = computed(() => map[props.status] ?? map.aman);
</script>

<template>
    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', cfg.css]">
        {{ cfg.label }}
    </span>
</template>
