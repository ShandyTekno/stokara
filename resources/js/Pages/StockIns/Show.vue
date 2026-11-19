<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

defineProps({ stockIn: Object });
</script>

<template>
    <Head :title="stockIn.transaction_number" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Detail Barang Masuk</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-center justify-between">
                <Link :href="route('stock-ins.index')" class="text-sm text-blue-800 hover:underline">← Kembali ke daftar</Link>
                <Link :href="route('stock-ins.create')" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Catat Lagi
                </Link>
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900">{{ stockIn.transaction_number }}</h3>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Tanggal</dt><dd class="font-medium">{{ stockIn.date }}</dd></div>
                    <div><dt class="text-gray-500">Pemasok</dt><dd class="font-medium">{{ stockIn.supplier ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Dicatat oleh</dt><dd class="font-medium">{{ stockIn.user ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Waktu dicatat</dt><dd class="font-medium">{{ stockIn.created_at }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Keterangan</dt><dd class="font-medium">{{ stockIn.notes ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 text-right">Jumlah dibeli</th>
                            <th class="px-4 py-3 text-right">Stok bertambah</th>
                            <th class="px-4 py-3 text-right">Harga / satuan terkecil</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in stockIn.details" :key="d.id">
                            <td class="px-4 py-3">
                                <Link v-if="!d.product_deleted" :href="route('products.show', d.product_id)" class="font-medium text-gray-900 hover:text-blue-800">
                                    {{ d.product }}
                                </Link>
                                <span v-else class="font-medium text-gray-500">{{ d.product }} (dihapus)</span>
                                <p class="text-xs text-gray-500">{{ d.code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ formatNumber(d.input_quantity) }} {{ d.is_package ? d.pack_unit : d.unit }}
                                <span v-if="d.is_package" class="block text-xs text-gray-500">isi {{ formatNumber(d.units_per_pack) }} {{ d.unit }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-green-600">+{{ formatNumber(d.quantity) }} {{ d.unit }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRupiah(d.unit_cost) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ formatRupiah(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-semibold">Total biaya</td>
                            <td class="px-4 py-3 text-right font-bold text-blue-900">{{ formatRupiah(stockIn.total_cost) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
