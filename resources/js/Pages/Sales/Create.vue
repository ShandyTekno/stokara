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
const isCash = computed(() => form.payment_method === 'tunai');
const change = computed(() => paid.value - total.value);
const insufficient = computed(() => isCash.value && paid.value < total.value);
const invalidQty = computed(() => form.items.some((l) => qty(l) < 1 || qty(l) > l.stock));
const canSubmit = computed(
    () => form.items.length > 0 && !invalidQty.value && !insufficient.value && !form.processing,
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
            <h2 class="text-lg font-semibold text-blue-900">Kasir Penjualan</h2>
        </template>

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-4 rounded-xl bg-white p-6 shadow-sm lg:col-span-2">
                <div>
                    <h3 class="font-semibold text-blue-900">Keranjang</h3>
                    <p class="text-sm text-gray-500">Barang dengan stok 0 tidak ditampilkan. Harga mengikuti harga jual terbaru di data barang.</p>
                </div>

                <ProductPicker :products="products" :exclude-ids="selectedIds" hide-empty @select="addProduct" />
                <InputError :message="form.errors.items" />

                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">Barang</th>
                                <th class="py-2 pr-3 text-right">Harga</th>
                                <th class="w-32 py-2 pr-3">Jumlah</th>
                                <th class="py-2 pr-3 text-right">Subtotal</th>
                                <th class="w-10 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="(line, i) in form.items" :key="line.product_id">
                                <tr class="align-top">
                                    <td class="py-3 pr-3">
                                        <p class="font-medium text-gray-900">{{ line.name }}</p>
                                        <p class="text-xs text-gray-500">{{ line.code }} · stok {{ formatNumber(line.stock) }} {{ line.unit }}</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right">{{ formatRupiah(line.price) }}</td>
                                    <td class="py-3 pr-3">
                                        <input v-model="line.quantity" type="number" min="1" :max="line.stock" :class="input" />
                                        <p v-if="qty(line) > line.stock" class="mt-1 text-xs text-red-600">Melebihi stok.</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right font-medium">{{ formatRupiah(subtotal(line)) }}</td>
                                    <td class="py-3 text-right">
                                        <button type="button" class="text-red-600 hover:underline" title="Hapus baris" @click="removeLine(i)">✕</button>
                                    </td>
                                </tr>
                                <tr v-if="lineErrors(i).length">
                                    <td colspan="5" class="pb-3 text-sm text-red-600">{{ lineErrors(i).join(' ') }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-md bg-gray-50 px-3 py-10 text-center text-sm text-gray-500">
                    Keranjang kosong. Cari dan pilih barang di atas.
                </p>
            </div>

            <aside class="h-fit space-y-5 rounded-xl bg-white p-6 shadow-sm lg:sticky lg:top-20">
                <div>
                    <p class="text-sm text-gray-500">Total belanja</p>
                    <p class="text-3xl font-bold text-blue-900">{{ formatRupiah(total) }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700">Metode pembayaran</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button
                            v-for="m in methods"
                            :key="m.value"
                            type="button"
                            :class="[
                                'rounded-md border px-3 py-2 text-sm font-medium',
                                form.payment_method === m.value
                                    ? 'border-blue-900 bg-blue-900 text-white'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50',
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
                        <label for="paid" class="block text-sm font-medium text-gray-700">Uang diterima</label>
                        <input id="paid" v-model="form.amount_paid" type="number" min="0" step="any" :class="input" />
                        <InputError class="mt-1" :message="form.errors.amount_paid" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="form.amount_paid = total">
                            Uang pas
                        </button>
                        <button
                            v-for="a in quickAmounts"
                            :key="a"
                            type="button"
                            class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50"
                            @click="form.amount_paid = a"
                        >
                            {{ formatRupiah(a) }}
                        </button>
                    </div>
                    <div
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            insufficient ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700',
                        ]"
                    >
                        <template v-if="insufficient">Uang kurang {{ formatRupiah(total - paid) }}</template>
                        <template v-else>Kembalian {{ formatRupiah(change) }}</template>
                    </div>
                </div>
                <p v-else class="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-900">
                    Pastikan pembayaran QRIS sudah diterima sebelum menyimpan. Aplikasi hanya mencatat metode pembayaran.
                </p>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                    <input id="notes" v-model="form.notes" type="text" :class="input" />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="w-full rounded-md bg-blue-900 px-4 py-3 font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                >
                    Simpan Penjualan
                </button>
                <Link :href="route('sales.index')" class="block text-center text-sm text-gray-500 hover:underline">Batal</Link>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
