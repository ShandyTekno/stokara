<script setup>
import { computed, ref } from 'vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({
    products: { type: Array, required: true },
    excludeIds: { type: Array, default: () => [] },
    hideEmpty: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Cari nama atau kode barang…' },
});
const emit = defineEmits(['select']);

const term = ref('');
const open = ref(false);

const results = computed(() => {
    const t = term.value.trim().toLowerCase();

    return props.products
        .filter((p) => !props.excludeIds.includes(p.id))
        .filter((p) => !props.hideEmpty || p.stock > 0)
        .filter((p) => !t || p.name.toLowerCase().includes(t) || p.code.toLowerCase().includes(t))
        .slice(0, 8);
});

function pick(product) {
    emit('select', product);
    term.value = '';
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <input
            v-model="term"
            type="search"
            :placeholder="placeholder"
            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800"
            @focus="open = true"
            @input="open = true"
            @blur="open = false"
        />
        <ul v-if="open" class="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded-md border bg-white shadow-lg">
            <li v-if="!results.length" class="px-3 py-2 text-sm text-gray-500">Tidak ada barang yang cocok.</li>
            <li
                v-for="p in results"
                :key="p.id"
                class="cursor-pointer px-3 py-2 hover:bg-blue-50"
                @mousedown.prevent="pick(p)"
            >
                <p class="text-sm font-medium text-gray-900">{{ p.name }}</p>
                <p class="text-xs text-gray-500">{{ p.code }} · stok {{ formatNumber(p.stock) }} {{ p.unit }}</p>
            </li>
        </ul>
    </div>
</template>
