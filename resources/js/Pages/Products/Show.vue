<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StockBadge from '@/Components/StockBadge.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

defineProps({ product: Object, movements: Array, can: Object });
const imageFailed = ref(false);
</script>

<template>
    <Head :title="product.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Detail Barang</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-center justify-between">
                <Link :href="route('products.index')" class="text-sm text-blue-800 hover:underline">← Kembali ke daftar</Link>
                <Link
                    v-if="can.manage"
                    :href="route('products.edit', product.id)"
                    class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                >
                    Ubah Barang
                </Link>
            </div>

            <section class="flex flex-col gap-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs sm:flex-row">
                <img
                    v-if="product.photo_url && !imageFailed"
                    :src="product.photo_url"
                    :alt="product.name"
                    class="h-40 w-40 rounded-2xl border border-slate-200 object-cover shadow-xs"
                    @error="imageFailed = true"
                />
                <div v-else class="flex h-40 w-40 items-center justify-center rounded-2xl bg-blue-50 text-4xl font-bold text-blue-700 ring-1 ring-blue-700/10 shadow-xs">
                    {{ product.name.charAt(0).toUpperCase() }}
                </div>

                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-xl font-bold text-gray-900">{{ product.name }}</h3>
                        <StockBadge :status="product.status" />
                    </div>
                    <p class="text-sm text-gray-500">{{ product.code }}</p>

                    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div><dt class="text-gray-500">Kategori</dt><dd class="font-medium">{{ product.category ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Pemasok</dt><dd class="font-medium">{{ product.supplier ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Stok</dt><dd class="font-medium">{{ formatNumber(product.stock) }} {{ product.unit }}</dd></div>
                        <div><dt class="text-gray-500">Stok minimum</dt><dd class="font-medium">{{ formatNumber(product.min_stock) }} {{ product.unit }}</dd></div>
                        <div><dt class="text-gray-500">Harga beli</dt><dd class="font-medium">{{ formatRupiah(product.purchase_price) }} / {{ product.unit }}</dd></div>
                        <div><dt class="text-gray-500">Harga jual</dt><dd class="font-medium">{{ formatRupiah(product.selling_price) }} / {{ product.unit }}</dd></div>
                        <div>
                            <dt class="text-gray-500">Margin per satuan</dt>
                            <dd class="font-medium">{{ formatRupiah(product.selling_price - product.purchase_price) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Kemasan</dt>
                            <dd class="font-medium">
                                <template v-if="product.pack_unit">1 {{ product.pack_unit }} = {{ formatNumber(product.units_per_pack) }} {{ product.unit }}</template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div><dt class="text-gray-500">Dibuat</dt><dd class="font-medium">{{ product.created_at }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold text-blue-900">Riwayat perubahan stok (10 terakhir)</h3>
                <p v-if="!movements.length" class="text-sm text-gray-500">Belum ada riwayat stok.</p>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Waktu</th>
                                <th class="py-2 pr-4">Jenis</th>
                                <th class="py-2 pr-4 text-right">Perubahan</th>
                                <th class="py-2 pr-4 text-right">Stok</th>
                                <th class="py-2 pr-4">Oleh</th>
                                <th class="py-2">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="m in movements" :key="m.id">
                                <td class="py-2 pr-4 text-gray-600">{{ m.time }}</td>
                                <td class="py-2 pr-4">{{ m.type }}</td>
                                <td :class="['py-2 pr-4 text-right font-semibold', m.change >= 0 ? 'text-green-600' : 'text-red-600']">
                                    {{ m.change >= 0 ? '+' : '' }}{{ formatNumber(m.change) }}
                                </td>
                                <td class="py-2 pr-4 text-right text-gray-600">{{ formatNumber(m.before) }} → {{ formatNumber(m.after) }}</td>
                                <td class="py-2 pr-4 text-gray-600">{{ m.user ?? 'Sistem' }}</td>
                                <td class="py-2 text-gray-500">{{ m.notes ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
