<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StockBadge from '@/Components/StockBadge.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({
    tab: String,
    counts: Object,
    products: Object,
    movements: Object,
    categories: Array,
    types: Array,
    filters: Object,
});

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const status = ref(props.filters.status ?? '');
const type = ref(props.filters.type ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
let timer = null;

watch([search, category, status, type, from, to], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const params =
            props.tab === 'riwayat'
                ? { tab: 'riwayat', search: search.value || undefined, type: type.value || undefined, from: from.value || undefined, to: to.value || undefined }
                : { search: search.value || undefined, category: category.value || undefined, status: status.value || undefined };

        router.get(route('monitoring.index'), params, { preserveState: true, replace: true });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

const tabClass = (active) => [
    'border-b-2 px-4 py-2 text-sm font-medium',
    active ? 'border-blue-900 text-blue-900' : 'border-transparent text-gray-500 hover:text-gray-700',
];

const field =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Monitoring Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Monitoring Stok</h2>
        </template>

        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Total jenis barang" :value="formatNumber(counts.total)" tone="blue" />
                <StatCard title="Stok aman" :value="formatNumber(counts.aman)" tone="green" />
                <StatCard title="Stok menipis" :value="formatNumber(counts.menipis)" tone="yellow" />
                <StatCard title="Stok habis" :value="formatNumber(counts.habis)" tone="red" />
            </div>

            <div
                v-if="counts.habis + counts.menipis > 0"
                class="flex flex-col gap-2 rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    <strong>Peringatan stok minimum:</strong>
                    {{ counts.habis }} barang habis dan {{ counts.menipis }} barang menipis (di bawah atau sama dengan stok minimum).
                </p>
                <div class="flex gap-3">
                    <Link v-if="counts.habis" :href="route('monitoring.index', { status: 'habis' })" class="font-medium text-red-700 underline">Lihat yang habis</Link>
                    <Link v-if="counts.menipis" :href="route('monitoring.index', { status: 'menipis' })" class="font-medium text-yellow-800 underline">Lihat yang menipis</Link>
                </div>
            </div>

            <div class="flex border-b">
                <Link :href="route('monitoring.index')" :class="tabClass(tab === 'stok')">Persediaan</Link>
                <Link :href="route('monitoring.index', { tab: 'riwayat' })" :class="tabClass(tab === 'riwayat')">Riwayat perubahan stok</Link>
            </div>

            <!-- Tab persediaan -->
            <template v-if="tab === 'stok' && products">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input v-model="search" type="search" placeholder="Cari nama atau kode barang…" :class="[field, 'w-full sm:max-w-xs']" />
                    <select v-model="category" :class="[field, 'w-full sm:w-48']">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="status" :class="[field, 'w-full sm:w-44']">
                        <option value="">Semua status</option>
                        <option value="aman">Stok aman</option>
                        <option value="menipis">Stok menipis</option>
                        <option value="habis">Stok habis</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3 text-right">Stok minimum</th>
                                <th class="px-4 py-3 text-right">Selisih dari minimum</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!products.data.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada barang yang cocok.</td>
                            </tr>
                            <tr v-for="p in products.data" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('products.show', p.id)" class="font-medium text-gray-900 hover:text-blue-800">{{ p.name }}</Link>
                                    <p class="text-xs text-gray-500">{{ p.code }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ p.category ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(p.stock) }} {{ p.unit }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ formatNumber(p.min_stock) }}</td>
                                <td :class="['px-4 py-3 text-right font-medium', p.gap < 0 ? 'text-red-600' : p.gap === 0 ? 'text-yellow-700' : 'text-green-600']">
                                    {{ p.gap > 0 ? '+' : '' }}{{ formatNumber(p.gap) }}
                                </td>
                                <td class="px-4 py-3"><StockBadge :status="p.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="products.links" :from="products.from ?? 0" :to="products.to ?? 0" :total="products.total" />
            </template>

            <!-- Tab riwayat -->
            <template v-else-if="tab === 'riwayat' && movements">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div>
                        <label class="block text-xs text-gray-500">Barang</label>
                        <input v-model="search" type="search" placeholder="Nama atau kode barang…" :class="[field, 'w-full sm:w-56']" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Jenis</label>
                        <select v-model="type" :class="[field, 'w-full sm:w-44']">
                            <option value="">Semua jenis</option>
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Dari tanggal</label>
                        <input v-model="from" type="date" :class="field" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Sampai tanggal</label>
                        <input v-model="to" type="date" :class="field" />
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3 text-right">Perubahan</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3">Oleh</th>
                                <th class="px-4 py-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!movements.data.length">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada riwayat pada filter ini.</td>
                            </tr>
                            <tr v-for="m in movements.data" :key="m.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600">{{ m.time }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ m.product ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ m.code }}</p>
                                </td>
                                <td class="px-4 py-3">{{ m.type }}</td>
                                <td :class="['px-4 py-3 text-right font-semibold', m.change >= 0 ? 'text-green-600' : 'text-red-600']">
                                    {{ m.change >= 0 ? '+' : '' }}{{ formatNumber(m.change) }} {{ m.unit }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ formatNumber(m.before) }} → {{ formatNumber(m.after) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ m.user }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ m.notes ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="movements.links" :from="movements.from ?? 0" :to="movements.to ?? 0" :total="movements.total" />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
