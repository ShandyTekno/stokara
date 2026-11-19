<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductPicker from '@/Components/ProductPicker.vue';
import InputError from '@/Components/InputError.vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({ products: Array, reasons: Array, today: String });

const form = useForm({
    transaction_date: props.today,
    reason: 'rusak',
    notes: '',
    items: [],
});

const selectedIds = computed(() => form.items.map((l) => l.product_id));

function addProduct(p) {
    form.items.push({
        product_id: p.id,
        name: p.name,
        code: p.code,
        unit: p.unit,
        stock: p.stock,
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
        ...data,
        items: data.items.map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
    })).post(route('stock-outs.store'));
}

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
const label = 'block text-sm font-medium text-gray-700';
</script>

<template>
    <Head title="Catat Barang Keluar" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Catat Barang Keluar</h2>
        </template>

        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <label for="date" :class="label">Tanggal</label>
                    <input id="date" v-model="form.transaction_date" type="date" :max="today" :class="input" required />
                    <InputError class="mt-1" :message="form.errors.transaction_date" />
                </div>
                <div>
                    <label for="reason" :class="label">Alasan pengeluaran</label>
                    <select id="reason" v-model="form.reason" :class="input">
                        <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.reason" />
                </div>
                <div>
                    <label for="notes" :class="label">
                        Keterangan {{ form.reason === 'lainnya' ? '(wajib)' : '(opsional)' }}
                    </label>
                    <input id="notes" v-model="form.notes" type="text" :class="input" />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>
            </div>

            <div class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <div>
                    <h3 class="font-semibold text-blue-900">Barang yang dikeluarkan</h3>
                    <p class="text-sm text-gray-500">
                        Jumlah dalam satuan terkecil. Barang dengan stok 0 tidak ditampilkan. Untuk penjualan, gunakan menu Penjualan.
                    </p>
                </div>

                <ProductPicker :products="products" :exclude-ids="selectedIds" hide-empty @select="addProduct" />
                <InputError :message="form.errors.items" />

                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">Barang</th>
                                <th class="py-2 pr-3 text-right">Stok tersedia</th>
                                <th class="w-40 py-2 pr-3">Jumlah keluar</th>
                                <th class="w-10 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="(line, i) in form.items" :key="line.product_id">
                                <tr class="align-top">
                                    <td class="py-3 pr-3">
                                        <p class="font-medium text-gray-900">{{ line.name }}</p>
                                        <p class="text-xs text-gray-500">{{ line.code }}</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right">{{ formatNumber(line.stock) }} {{ line.unit }}</td>
                                    <td class="py-3 pr-3">
                                        <div class="flex items-center gap-2">
                                            <input v-model="line.quantity" type="number" min="1" :max="line.stock" :class="input" />
                                            <span class="mt-1 text-gray-500">{{ line.unit }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right">
                                        <button type="button" class="text-red-600 hover:underline" title="Hapus baris" @click="removeLine(i)">✕</button>
                                    </td>
                                </tr>
                                <tr v-if="lineErrors(i).length">
                                    <td colspan="4" class="pb-3 text-sm text-red-600">{{ lineErrors(i).join(' ') }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-md bg-gray-50 px-3 py-6 text-center text-sm text-gray-500">
                    Belum ada barang. Cari dan pilih barang di atas.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link :href="route('stock-outs.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing || !form.items.length"
                    class="rounded-md bg-blue-900 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                >
                    Simpan Barang Keluar
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
