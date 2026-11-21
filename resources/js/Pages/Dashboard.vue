<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StockBadge from '@/Components/StockBadge.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({
    stats: Object,
    salesChart: Object,
    movementChart: Object,
    lowStock: Array,
    activities: Array,
});

const salesDatasets = computed(() => [{ label: 'Penjualan', data: props.salesChart.values }]);
const movementDatasets = computed(() => [
    { label: 'Barang masuk', data: props.movementChart.masuk, backgroundColor: '#10b981' },
    { label: 'Barang keluar', data: props.movementChart.keluar, backgroundColor: '#f43f5e' },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Dashboard</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Ringkasan Bisnis
                </span>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stat Cards Grid -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard title="Total jenis barang" :value="formatNumber(stats.total_products)" tone="blue" />
                <StatCard
                    title="Total stok tersedia"
                    :value="formatNumber(stats.total_stock)"
                    hint="Jumlah seluruh satuan terkecil"
                    tone="green"
                />
                <StatCard title="Barang hampir habis" :value="formatNumber(stats.low_stock_count)" tone="yellow" />
                <StatCard title="Barang habis" :value="formatNumber(stats.out_of_stock_count)" tone="red" />
                <StatCard title="Transaksi hari ini" :value="formatNumber(stats.today_transactions)" tone="blue" />
                <StatCard title="Penjualan hari ini" :value="formatRupiah(stats.today_sales)" tone="blue" />
            </div>

            <!-- Charts Section -->
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs card-hover">
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Penjualan 7 Hari Terakhir</h3>
                            <p class="text-xs text-slate-500">Tren omset penjualan toko harian</p>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <LineChart :labels="salesChart.labels" :datasets="salesDatasets" currency />
                </section>

                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs card-hover">
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Barang Masuk & Keluar</h3>
                            <p class="text-xs text-slate-500">Volume pergerakan fisik barang 7 hari terakhir</p>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                    </div>
                    <BarChart :labels="movementChart.labels" :datasets="movementDatasets" />
                </section>
            </div>

            <!-- Low Stock & Recent Activities -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Low Stock Card -->
                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs card-hover">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Perhatian: Stok Rendah</h3>
                            <p class="text-xs text-slate-500">Barang yang segera perlu pengadaan ulang</p>
                        </div>
                        <Link
                            :href="route('monitoring.index')"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors hover:underline"
                        >
                            Lihat Semua →
                        </Link>
                    </div>

                    <div v-if="!lowStock.length" class="flex flex-col items-center justify-center py-10 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 mb-2">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700">Semua stok masih aman</p>
                        <p class="text-xs text-slate-400">Tidak ada barang di bawah batas minimum.</p>
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="p in lowStock"
                            :key="p.id"
                            class="group flex items-center justify-between gap-3 rounded-xl py-3 px-2 transition-colors hover:bg-slate-50/80"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="route('products.show', p.id)"
                                    class="block truncate text-sm font-semibold text-slate-800 transition-colors group-hover:text-blue-700"
                                >
                                    {{ p.name }}
                                </Link>
                                <p class="text-xs text-slate-500">
                                    {{ p.code }} · Tersisa <span class="font-semibold text-slate-700">{{ formatNumber(p.stock) }} {{ p.unit }}</span> (min. {{ formatNumber(p.min_stock) }})
                                </p>
                            </div>
                            <StockBadge :status="p.status" />
                        </li>
                    </ul>
                </section>

                <!-- Activities Card -->
                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs card-hover">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Aktivitas Terakhir</h3>
                            <p class="text-xs text-slate-500">Riwayat transaksi dan perpindahan barang</p>
                        </div>
                        <Link
                            :href="route('activities.index')"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors hover:underline"
                        >
                            Riwayat Lengkap →
                        </Link>
                    </div>

                    <div v-if="!activities.length" class="flex flex-col items-center justify-center py-10 text-center">
                        <p class="text-sm font-medium text-slate-500">Belum ada aktivitas stok</p>
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="a in activities"
                            :key="a.id"
                            class="flex items-center justify-between gap-3 rounded-xl py-3 px-2 transition-colors hover:bg-slate-50/80"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">
                                    <span class="font-semibold text-slate-900">{{ a.type }}</span>: {{ a.product }}
                                </p>
                                <p class="text-xs text-slate-400">{{ a.user ?? 'Sistem' }} · {{ a.time }}</p>
                            </div>
                            <span
                                :class="[
                                    'shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold transition-transform hover:scale-105',
                                    a.change >= 0
                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'
                                        : 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
                                ]"
                            >
                                {{ a.change >= 0 ? '+' : '' }}{{ formatNumber(a.change) }} {{ a.unit }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
