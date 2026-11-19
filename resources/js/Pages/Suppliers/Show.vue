<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StockBadge from '@/Components/StockBadge.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

defineProps({ supplier: Object, tab: String, summary: Object, products: Array, history: Object });

const tabClass = (active) =>
    [
        'border-b-2 px-4 py-2 text-sm font-medium',
        active ? 'border-blue-900 text-blue-900' : 'border-transparent text-gray-500 hover:text-gray-700',
    ];
</script>

<template>
    <Head :title="supplier.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Detail Pemasok</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-center justify-between">
                <Link :href="route('suppliers.index')" class="text-sm text-blue-800 hover:underline">← Kembali ke daftar</Link>
                <Link :href="route('suppliers.edit', supplier.id)" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    Ubah Pemasok
                </Link>
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900">{{ supplier.name }}</h3>
                <p v-if="supplier.company_name" class="text-sm text-gray-500">{{ supplier.company_name }}</p>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Telepon</dt><dd class="font-medium">{{ supplier.phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Alamat</dt><dd class="font-medium">{{ supplier.address ?? '—' }}</dd></div>
                </dl>
            </section>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total pengadaan</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ formatNumber(summary.total_transactions) }}</p>
                </div>
                <div class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total belanja</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ formatRupiah(summary.total_spent) }}</p>
                </div>
                <div class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Pengadaan terakhir</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ summary.last_date ?? '—' }}</p>
                </div>
            </div>

            <div class="flex border-b">
                <Link :href="route('suppliers.show', supplier.id)" preserve-state :class="tabClass(tab === 'info')">Barang dari pemasok</Link>
                <Link :href="route('suppliers.show', { supplier: supplier.id, tab: 'pengadaan' })" preserve-state :class="tabClass(tab === 'pengadaan')">
                    Riwayat pengadaan
                </Link>
            </div>

            <section v-if="tab === 'info'" class="rounded-xl bg-white p-6 shadow-sm">
                <p v-if="!products.length" class="text-sm text-gray-500">Belum ada barang yang terhubung dengan pemasok ini.</p>
                <ul v-else class="divide-y">
                    <li v-for="p in products" :key="p.id" class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <Link :href="route('products.show', p.id)" class="block truncate text-sm font-medium text-gray-900 hover:text-blue-800">{{ p.name }}</Link>
                            <p class="text-xs text-gray-500">{{ p.code }} · stok {{ formatNumber(p.stock) }} {{ p.unit }}</p>
                        </div>
                        <StockBadge :status="p.status" />
                    </li>
                </ul>
            </section>

            <section v-else class="space-y-4">
                <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No. transaksi</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-right">Jenis barang</th>
                                <th class="px-4 py-3 text-right">Total biaya</th>
                                <th class="px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!history.data.length">
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada riwayat pengadaan dari pemasok ini.</td>
                            </tr>
                            <tr v-for="h in history.data" :key="h.id">
                                <td class="px-4 py-3 font-medium">{{ h.transaction_number }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ h.date }}</td>
                                <td class="px-4 py-3 text-right">{{ formatNumber(h.items) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatRupiah(h.total_cost) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ h.notes ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="history.links" :from="history.from ?? 0" :to="history.to ?? 0" :total="history.total" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
