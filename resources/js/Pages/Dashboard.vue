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
    { label: 'Barang masuk', data: props.movementChart.masuk, backgroundColor: '#16a34a' },
    { label: 'Barang keluar', data: props.movementChart.keluar, backgroundColor: '#dc2626' },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Dashboard</h2>
        </template>

        <div class="space-y-6">
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

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold text-blue-900">Penjualan 7 hari terakhir</h3>
                    <LineChart :labels="salesChart.labels" :datasets="salesDatasets" currency />
                </section>
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold text-blue-900">Barang masuk dan keluar (7 hari)</h3>
                    <BarChart :labels="movementChart.labels" :datasets="movementDatasets" />
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold text-blue-900">Barang dengan stok rendah</h3>
                    <p v-if="!lowStock.length" class="text-sm text-gray-500">Semua stok masih aman.</p>
                    <ul v-else class="divide-y">
                        <li v-for="p in lowStock" :key="p.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <Link :href="route('products.show', p.id)" class="block truncate text-sm font-medium text-gray-900 hover:text-blue-800">
                                    {{ p.name }}
                                </Link>
                                <p class="text-xs text-gray-500">
                                    {{ p.code }} · stok {{ formatNumber(p.stock) }} {{ p.unit }} (min. {{ formatNumber(p.min_stock) }})
                                </p>
                            </div>
                            <StockBadge :status="p.status" />
                        </li>
                    </ul>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold text-blue-900">Aktivitas terbaru</h3>
                    <p v-if="!activities.length" class="text-sm text-gray-500">Belum ada aktivitas stok.</p>
                    <ul v-else class="divide-y">
                        <li v-for="a in activities" :key="a.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-gray-900">{{ a.type }}: {{ a.product }}</p>
                                <p class="text-xs text-gray-500">{{ a.user ?? 'Sistem' }} · {{ a.time }}</p>
                            </div>
                            <span :class="['shrink-0 text-sm font-semibold', a.change >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ a.change >= 0 ? '+' : '' }}{{ formatNumber(a.change) }} {{ a.unit }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
