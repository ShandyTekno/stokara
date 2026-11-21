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
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Monitoring Stok</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Real-time
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Total jenis barang" :value="formatNumber(counts.total)" tone="blue" />
                <StatCard title="Stok aman" :value="formatNumber(counts.aman)" tone="green" />
                <StatCard title="Stok menipis" :value="formatNumber(counts.menipis)" tone="yellow" />
                <StatCard title="Stok habis" :value="formatNumber(counts.habis)" tone="red" />
            </div>

            <div
                v-if="counts.habis + counts.menipis > 0"
                class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <p>
                        <strong class="font-semibold">Peringatan stok minimum:</strong>
                        {{ counts.habis }} barang habis dan {{ counts.menipis }} barang menipis (di bawah atau sama dengan stok minimum).
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <Link
                        v-if="counts.habis"
                        :href="route('monitoring.index', { status: 'habis' })"
                        class="rounded-lg bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800 hover:bg-rose-200 transition-colors"
                    >
                        Lihat Habis ({{ counts.habis }})
                    </Link>
                    <Link
                        v-if="counts.menipis"
                        :href="route('monitoring.index', { status: 'menipis' })"
                        class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 hover:bg-amber-200 transition-colors"
                    >
                        Lihat Menipis ({{ counts.menipis }})
                    </Link>
                </div>
            </div>

            <div class="flex gap-2 border-b border-slate-200 pb-px">
                <Link
                    :href="route('monitoring.index')"
                    :class="[
                        'rounded-t-xl px-4 py-2.5 text-sm font-semibold transition-all',
                        tab === 'stok'
                            ? 'border-b-2 border-blue-700 text-blue-700 bg-white/60 shadow-xs'
                            : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100/60',
                    ]"
                >
                    Status Persediaan
                </Link>
                <Link
                    :href="route('monitoring.index', { tab: 'riwayat' })"
                    :class="[
                        'rounded-t-xl px-4 py-2.5 text-sm font-semibold transition-all',
                        tab === 'riwayat'
                            ? 'border-b-2 border-blue-700 text-blue-700 bg-white/60 shadow-xs'
                            : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100/60',
                    ]"
                >
                    Riwayat Perubahan Stok
                </Link>
            </div>

            <!-- Tab persediaan -->
            <template v-if="tab === 'stok' && products">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau kode barang…"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:max-w-xs"
                    />
                    <select
                        v-model="category"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:w-48"
                    >
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select
                        v-model="status"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:w-44"
                    >
                        <option value="">Semua status</option>
                        <option value="aman">Stok aman</option>
                        <option value="menipis">Stok menipis</option>
                        <option value="habis">Stok habis</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-xs">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3.5">Barang</th>
                                <th class="px-5 py-3.5">Kategori</th>
                                <th class="px-5 py-3.5 text-right">Stok</th>
                                <th class="px-5 py-3.5 text-right">Stok Minimum</th>
                                <th class="px-5 py-3.5 text-right">Selisih</th>
                                <th class="px-5 py-3.5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!products.data.length">
                                <td colspan="6" class="px-5 py-12 text-center text-slate-400">Tidak ada barang yang cocok.</td>
                            </tr>
                            <tr v-for="p in products.data" :key="p.id" class="transition-colors hover:bg-slate-50/80">
                                <td class="px-5 py-3.5">
                                    <Link :href="route('products.show', p.id)" class="font-semibold text-slate-800 hover:text-blue-700 transition-colors">
                                        {{ p.name }}
                                    </Link>
                                    <p class="text-xs text-slate-400 font-mono">{{ p.code }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ p.category ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-800">{{ formatNumber(p.stock) }} <span class="text-xs font-normal text-slate-400">{{ p.unit }}</span></td>
                                <td class="px-5 py-3.5 text-right text-slate-600">{{ formatNumber(p.min_stock) }}</td>
                                <td :class="['px-5 py-3.5 text-right font-bold', p.gap < 0 ? 'text-rose-600' : p.gap === 0 ? 'text-amber-600' : 'text-emerald-600']">
                                    {{ p.gap > 0 ? '+' : '' }}{{ formatNumber(p.gap) }}
                                </td>
                                <td class="px-5 py-3.5"><StockBadge :status="p.status" /></td>
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
