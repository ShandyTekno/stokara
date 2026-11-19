<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'blue' },
});

const accent = computed(
    () =>
        ({
            blue: 'border-blue-900 text-blue-900',
            green: 'border-green-500 text-green-700',
            yellow: 'border-yellow-400 text-yellow-700',
            red: 'border-red-500 text-red-700',
        })[props.tone] ?? 'border-blue-900 text-blue-900',
);
</script>

<template>
    <div :class="['rounded-xl border-l-4 bg-white p-5 shadow-sm', accent]">
        <p class="text-sm font-medium text-gray-500">{{ title }}</p>
        <p class="mt-2 text-2xl font-bold">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
    </div>
</template>
