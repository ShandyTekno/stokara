<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

defineProps({ sale: Object, store: Object });

const printReceipt = () => window.print();
</script>

<template>
    <Head :title="sale.invoice_number" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Detail Penjualan</h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">
            <div class="flex items-center justify-between print:hidden">
                <Link :href="route('sales.index')" class="text-sm text-blue-800 hover:underline">← Kembali ke daftar</Link>
                <div class="flex gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="printReceipt">
                        Cetak struk
                    </button>
                    <Link :href="route('sales.create')" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        + Transaksi Baru
                    </Link>
                </div>
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm print:shadow-none">
                <div class="border-b border-dashed pb-4 text-center">
                    <h3 class="text-lg font-bold text-gray-900">{{ store.name }}</h3>
                    <p class="text-sm text-gray-500">{{ sale.invoice_number }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 border-b border-dashed py-4 text-sm">
                    <dt class="text-gray-500">Waktu</dt><dd class="text-right font-medium">{{ sale.sold_at }}</dd>
                    <dt class="text-gray-500">Kasir</dt><dd class="text-right font-medium">{{ sale.cashier ?? '—' }}</dd>
                    <dt class="text-gray-500">Pembayaran</dt><dd class="text-right font-medium">{{ sale.payment }}</dd>
                    <template v-if="sale.notes">
                        <dt class="text-gray-500">Catatan</dt><dd class="text-right font-medium">{{ sale.notes }}</dd>
                    </template>
                </dl>

                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">Barang</th>
                            <th class="py-2 pr-2 text-right">Jml</th>
                            <th class="py-2 pr-2 text-right">Harga</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in sale.details" :key="d.id" class="align-top">
                            <td class="py-2 pr-2">
                                <Link
                                    v-if="!d.product_deleted"
                                    :href="route('products.show', d.product_id)"
                                    class="font-medium text-gray-900 hover:text-blue-800 print:no-underline"
                                >
                                    {{ d.product }}
                                </Link>
                                <span v-else class="font-medium text-gray-500">{{ d.product }} (dihapus)</span>
                            </td>
                            <td class="py-2 pr-2 text-right">{{ formatNumber(d.quantity) }} {{ d.unit }}</td>
                            <td class="py-2 pr-2 text-right">{{ formatRupiah(d.unit_price) }}</td>
                            <td class="py-2 text-right font-medium">{{ formatRupiah(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>

                <dl class="mt-4 space-y-1 border-t border-dashed pt-4 text-sm">
                    <div class="flex justify-between text-base font-bold text-blue-900">
                        <dt>Total</dt><dd>{{ formatRupiah(sale.total_amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ sale.payment_value === 'tunai' ? 'Tunai' : 'QRIS' }}</dt>
                        <dd class="font-medium">{{ formatRupiah(sale.amount_paid) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Kembalian</dt>
                        <dd class="font-medium">{{ formatRupiah(sale.change_amount) }}</dd>
                    </div>
                </dl>

                <p class="mt-6 text-center text-xs text-gray-400">Terima kasih atas kunjungan Anda.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
