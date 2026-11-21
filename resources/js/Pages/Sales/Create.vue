<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductPicker from '@/Components/ProductPicker.vue';
import InputError from '@/Components/InputError.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

defineProps({ products: Array });

const form = useForm({
    payment_method: 'tunai',
    amount_paid: '',
    notes: '',
    items: [],
});

const methods = [
    { value: 'tunai', label: 'Tunai' },
    { value: 'qris', label: 'QRIS' },
];
const quickAmounts = [20000, 50000, 100000];

const selectedIds = computed(() => form.items.map((l) => l.product_id));
const qty = (line) => Number(line.quantity) || 0;
const subtotal = (line) => qty(line) * line.price;
const total = computed(() => form.items.reduce((sum, l) => sum + subtotal(l), 0));
const paid = computed(() => Number(form.amount_paid) || 0);
const hasPaidInput = computed(
    () => form.amount_paid !== '' && form.amount_paid !== null && form.amount_paid !== undefined,
);
const isCash = computed(() => form.payment_method === 'tunai');
const change = computed(() => paid.value - total.value);
const insufficient = computed(() => isCash.value && hasPaidInput.value && paid.value < total.value);
const invalidQty = computed(() => form.items.some((l) => qty(l) < 1 || qty(l) > l.stock));
const canSubmit = computed(
    () =>
        form.items.length > 0 &&
        !invalidQty.value &&
        (!isCash.value || (hasPaidInput.value && !insufficient.value)) &&
        !form.processing,
);

function addProduct(p) {
    form.items.push({
        product_id: p.id,
        name: p.name,
        code: p.code,
        unit: p.unit,
        stock: p.stock,
        price: p.selling_price,
        quantity: 1,
    });
}

function incrementQty(line) {
    const current = Number(line.quantity) || 0;
    if (current < line.stock) {
        line.quantity = current + 1;
    }
}

function decrementQty(line) {
    const current = Number(line.quantity) || 1;
    if (current > 1) {
        line.quantity = current - 1;
    }
}

const removeLine = (index) => form.items.splice(index, 1);

const lineErrors = (index) =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith(`items.${index}.`))
        .map(([, message]) => message);

function submit() {
    form.transform((data) => ({
        payment_method: data.payment_method,
        amount_paid: data.payment_method === 'tunai' ? data.amount_paid : null,
        notes: data.notes,
        items: data.items.map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
    })).post(route('sales.store'));
}

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Kasir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Kasir Penjualan</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    POS
                </span>
            </div>
        </template>

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
            <!-- Keranjang Card -->
            <div class="space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs lg:col-span-2">
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="font-bold text-slate-900">Keranjang Belanja</h3>
                    <p class="text-xs text-slate-500">Pilih barang dan sesuaikan jumlah pembelian menggunakan tombol − dan +.</p>
                </div>

                <ProductPicker :products="products" :exclude-ids="selectedIds" hide-empty @select="addProduct" />
                <InputError :message="form.errors.items" />

                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="py-2.5 pr-3">Barang</th>
                                <th class="py-2.5 pr-3 text-right">Harga</th>
                                <th class="w-36 py-2.5 pr-3">Jumlah</th>
                                <th class="py-2.5 pr-3 text-right">Subtotal</th>
                                <th class="w-10 py-2.5" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="(line, i) in form.items" :key="line.product_id">
                                <tr class="align-top hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3 pr-3">
                                        <p class="font-semibold text-slate-800">{{ line.name }}</p>
                                        <p class="text-xs text-slate-400 font-mono">{{ line.code }} · stok {{ formatNumber(line.stock) }} {{ line.unit }}</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right text-slate-600 font-medium">{{ formatRupiah(line.price) }}</td>
                                    <td class="py-3 pr-3">
                                        <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50/50 shadow-xs overflow-hidden">
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center bg-slate-50 text-base font-bold text-slate-600 transition hover:bg-slate-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="qty(line) <= 1"
                                                title="Kurang 1"
                                                @click="decrementQty(line)"
                                            >
                                                −
                                            </button>
                                            <input
                                                v-model="line.quantity"
                                                type="number"
                                                min="1"
                                                :max="line.stock"
                                                class="h-8 w-14 border-0 border-x border-slate-200 bg-white p-0 text-center text-sm font-semibold text-slate-800 focus:ring-1 focus:ring-blue-600 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center bg-slate-50 text-base font-bold text-slate-600 transition hover:bg-slate-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="qty(line) >= line.stock"
                                                title="Tambah 1"
                                                @click="incrementQty(line)"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <p v-if="qty(line) > line.stock" class="mt-1 text-xs text-rose-600 font-medium">Melebihi stok.</p>
                                        <p v-else-if="qty(line) < 1" class="mt-1 text-xs text-rose-600 font-medium">Minimal 1.</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right font-bold text-slate-900">{{ formatRupiah(subtotal(line)) }}</td>
                                    <td class="py-3 text-right">
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-colors"
                                            title="Hapus baris"
                                            @click="removeLine(i)"
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="lineErrors(i).length">
                                    <td colspan="5" class="pb-3 text-sm text-rose-600">{{ lineErrors(i).join(' ') }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-12 px-4 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Keranjang masih kosong</p>
                    <p class="text-xs text-slate-400 mt-0.5">Cari dan pilih barang pada kolom pencarian di atas.</p>
                </div>
            </div>

            <!-- Ringkasan & Pembayaran Aside -->
            <aside class="h-fit space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs card-hover lg:sticky lg:top-20">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tagihan</p>
                    <p class="mt-1 text-3xl font-extrabold text-blue-900 tracking-tight">{{ formatRupiah(total) }}</p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Metode Pembayaran</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="m in methods"
                            :key="m.value"
                            type="button"
                            :class="[
                                'rounded-xl border px-3 py-2.5 text-sm font-semibold transition-all',
                                form.payment_method === m.value
                                    ? 'border-blue-700 bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-xs'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50',
                            ]"
                            @click="form.payment_method = m.value"
                        >
                            {{ m.label }}
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.payment_method" />
                </div>

                <div v-if="isCash" class="space-y-3">
                    <div>
                        <label for="paid" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Uang Diterima</label>
                        <input
                            id="paid"
                            v-model="form.amount_paid"
                            type="number"
                            min="0"
                            step="any"
                            placeholder="0"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm font-semibold text-slate-800 shadow-xs focus:border-blue-600 focus:ring-blue-600"
                        />
                        <InputError class="mt-1" :message="form.errors.amount_paid" />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 active:scale-95"
                            @click="form.amount_paid = total"
                        >
                            Uang pas
                        </button>
                        <button
                            v-for="a in quickAmounts"
                            :key="a"
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95"
                            @click="form.amount_paid = a"
                        >
                            {{ formatRupiah(a) }}
                        </button>
                    </div>
                    <div
                        :class="[
                            'rounded-xl px-3.5 py-2.5 text-sm font-semibold transition-colors',
                            !hasPaidInput
                                ? 'bg-slate-100 text-slate-600 border border-slate-200/60'
                                : insufficient
                                    ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                    : 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                        ]"
                    >
                        <template v-if="!hasPaidInput">Masukkan uang diterima</template>
                        <template v-else-if="insufficient">Uang kurang {{ formatRupiah(total - paid) }}</template>
                        <template v-else>Kembalian {{ formatRupiah(change) }}</template>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-blue-200 bg-blue-50/80 p-3.5 text-xs text-blue-900">
                    <p class="font-semibold">Pembayaran QRIS</p>
                    <p class="mt-0.5 text-blue-800/80">Pastikan scan QRIS kasir telah terbayar di ponsel pelanggan sebelum menyimpan transaksi.</p>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Catatan (opsional)</label>
                    <input
                        id="notes"
                        v-model="form.notes"
                        type="text"
                        placeholder="Contoh: Titipan tetangga"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600"
                    />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="w-full rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-3.5 font-bold text-white shadow-md shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Simpan Penjualan
                </button>
                <Link :href="route('sales.index')" class="block text-center text-xs font-medium text-slate-500 hover:text-slate-800 hover:underline">
                    Batal
                </Link>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
