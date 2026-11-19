<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber } from '@/utils/format';

defineProps({ stockOut: Object });
</script>

<template>
    <Head :title="stockOut.transaction_number" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Detail Barang Keluar</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center justify-between">
                <Link :href="route('stock-outs.index')" class="text-sm text-blue-800 hover:underline">← Kembali ke daftar</Link>
                <Link :href="route('stock-outs.create')" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Catat Lagi
                </Link>
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900">{{ stockOut.transaction_number }}</h3>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Tanggal</dt><dd class="font-medium">{{ stockOut.date }}</dd></div>
                    <div><dt class="text-gray-500">Alasan</dt><dd class="font-medium">{{ stockOut.reason }}</dd></div>
                    <div><dt class="text-gray-500">Dicatat oleh</dt><dd class="font-medium">{{ stockOut.user ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Waktu dicatat</dt><dd class="font-medium">{{ stockOut.created_at }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Keterangan</dt><dd class="font-medium">{{ stockOut.notes ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 text-right">Jumlah keluar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in stockOut.details" :key="d.id">
                            <td class="px-4 py-3">
                                <Link v-if="!d.product_deleted" :href="route('products.show', d.product_id)" class="font-medium text-gray-900 hover:text-blue-800">
                                    {{ d.product }}
                                </Link>
                                <span v-else class="font-medium text-gray-500">{{ d.product }} (dihapus)</span>
                                <p class="text-xs text-gray-500">{{ d.code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-red-600">−{{ formatNumber(d.quantity) }} {{ d.unit }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
