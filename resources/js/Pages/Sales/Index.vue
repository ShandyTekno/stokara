<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({ sales: Object, summary: Object, filters: Object });

const search = ref(props.filters.search ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const payment = ref(props.filters.payment ?? '');
let timer = null;

watch([search, from, to, payment], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('sales.index'),
            {
                search: search.value || undefined,
                from: from.value || undefined,
                to: to.value || undefined,
                payment: payment.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

function reset() {
    search.value = '';
    from.value = '';
    to.value = '';
    payment.value = '';
}

const field =
    'rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600';
</script>

<template>
    <Head title="Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Riwayat Penjualan</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Kasir
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <!-- Filter Toolbar -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Cari Invoice</label>
                        <input v-model="search" type="search" placeholder="No. invoice…" :class="[field, 'w-full sm:w-52']" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                        <input v-model="from" type="date" :class="field" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                        <input v-model="to" type="date" :class="field" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Metode</label>
                        <select v-model="payment" :class="field">
                            <option value="">Semua Metode</option>
                            <option value="tunai">Tunai</option>
                            <option value="qris">QRIS</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 active:scale-95 transition-all"
                        @click="reset"
                    >
                        Reset Filter
                    </button>
                </div>
                <Link
                    :href="route('sales.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-2.5 text-center text-sm font-bold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Transaksi Baru
                </Link>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Jumlah transaksi" :value="formatNumber(summary.count)" tone="blue" />
                <StatCard title="Total penjualan" :value="formatRupiah(summary.total_amount)" tone="blue" />
                <StatCard title="Penjualan Tunai" :value="formatRupiah(summary.cash_total)" tone="green" />
                <StatCard title="Penjualan QRIS" :value="formatRupiah(summary.qris_total)" tone="blue" />
            </div>

            <!-- Modern Table Container -->
            <div class="modern-table-box">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">No. Invoice</th>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5 text-right">Jenis Barang</th>
                            <th class="px-5 py-3.5 text-right">Total Item</th>
                            <th class="px-5 py-3.5 text-right">Total Transaksi</th>
                            <th class="px-5 py-3.5">Pembayaran</th>
                            <th class="px-5 py-3.5">Kasir</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                {{ filters.search || filters.from || filters.to || filters.payment ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada data penjualan.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="s in sales.data"
                            :key="s.id"
                            class="group table-row-modern"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="route('sales.show', s.id)"
                                    class="code-pill font-semibold"
                                >
                                    {{ s.invoice_number }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500 font-medium whitespace-nowrap">{{ s.sold_at }}</td>
                            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatNumber(s.items) }} item</td>
                            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatNumber(s.total_units) }} pcs</td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-900">{{ formatRupiah(s.total_amount) }}</td>
                            <td class="px-5 py-3.5">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-colors',
                                        s.payment_value === 'tunai'
                                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'
                                            : 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            s.payment_value === 'tunai' ? 'bg-emerald-500' : 'bg-blue-500',
                                        ]"
                                    />
                                    {{ s.payment }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">
                                        {{ (s.user ?? 'S').charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="truncate text-xs font-medium">{{ s.user ?? 'Sistem' }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="route('sales.show', s.id)"
                                    class="btn-table-action"
                                >
                                    <span>Detail</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="sales.links" :from="sales.from ?? 0" :to="sales.to ?? 0" :total="sales.total" />
        </div>
    </AuthenticatedLayout>
</template>
