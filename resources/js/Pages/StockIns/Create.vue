<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductPicker from '@/Components/ProductPicker.vue';
import InputError from '@/Components/InputError.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({ products: Array, suppliers: Array, today: String });

const form = useForm({
    transaction_date: props.today,
    supplier_id: '',
    notes: '',
    items: [],
});

const factor = (line) => (line.is_package ? line.units_per_pack : 1);
const selectedIds = computed(() => form.items.map((l) => l.product_id));
const subtotal = (line) => (Number(line.quantity) || 0) * (Number(line.price) || 0);
const total = computed(() => form.items.reduce((sum, l) => sum + subtotal(l), 0));

function addProduct(p) {
    form.items.push({
        product_id: p.id,
        name: p.name,
        code: p.code,
        unit: p.unit,
        pack_unit: p.pack_unit,
        units_per_pack: p.units_per_pack,
        stock: p.stock,
        quantity: 1,
        is_package: false,
        price: p.purchase_price,
    });
}

function removeLine(index) {
    form.items.splice(index, 1);
}

// Harga ikut dikonversi saat satuan diganti agar harga per satuan terkecil tetap sama.
function setUnit(line, isPackage) {
    const perSmallest = (Number(line.price) || 0) / factor(line);
    line.is_package = isPackage;
    line.price = Math.round(perSmallest * factor(line));
}

const lineErrors = (index) =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith(`items.${index}.`))
        .map(([, message]) => message);

function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.map((l) => ({
            product_id: l.product_id,
            quantity: l.quantity,
            is_package: l.is_package,
            price: l.price,
        })),
    })).post(route('stock-ins.store'));
}

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
const label = 'block text-sm font-medium text-gray-700';
</script>

<template>
    <Head title="Catat Barang Masuk" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Catat Barang Masuk</h2>
        </template>

        <form class="mx-auto max-w-5xl space-y-6" @submit.prevent="submit">
            <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <label for="date" :class="label">Tanggal</label>
                    <input id="date" v-model="form.transaction_date" type="date" :max="today" :class="input" required />
                    <InputError class="mt-1" :message="form.errors.transaction_date" />
                </div>
                <div>
                    <label for="supplier" :class="label">Pemasok</label>
                    <select id="supplier" v-model="form.supplier_id" :class="input">
                        <option value="">— Tanpa pemasok —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.supplier_id" />
                </div>
                <div>
                    <label for="notes" :class="label">Keterangan (opsional)</label>
                    <input id="notes" v-model="form.notes" type="text" :class="input" />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>
            </div>

            <div class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <div>
                    <h3 class="font-semibold text-blue-900">Barang yang dibeli</h3>
                    <p class="text-sm text-gray-500">
                        Harga diisi per satuan yang dipilih. Stok selalu bertambah dalam satuan terkecil.
                    </p>
                </div>

                <ProductPicker :products="products" :exclude-ids="selectedIds" @select="addProduct" />
                <InputError :message="form.errors.items" />

                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">Barang</th>
                                <th class="w-28 py-2 pr-3">Jumlah</th>
                                <th class="w-36 py-2 pr-3">Satuan</th>
                                <th class="w-40 py-2 pr-3">Harga beli</th>
                                <th class="py-2 pr-3 text-right">Subtotal</th>
                                <th class="w-10 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="(line, i) in form.items" :key="line.product_id">
                                <tr class="align-top">
                                    <td class="py-3 pr-3">
                                        <p class="font-medium text-gray-900">{{ line.name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ line.code }} · stok {{ formatNumber(line.stock) }} {{ line.unit }}
                                        </p>
                                        <p class="text-xs text-green-700">
                                            + {{ formatNumber((Number(line.quantity) || 0) * factor(line)) }} {{ line.unit }}
                                        </p>
                                    </td>
                                    <td class="py-3 pr-3">
                                        <input v-model="line.quantity" type="number" min="1" :class="input" />
                                    </td>
                                    <td class="py-3 pr-3">
                                        <select
                                            :value="line.is_package ? 'pack' : 'unit'"
                                            :class="input"
                                            @change="setUnit(line, $event.target.value === 'pack')"
                                        >
                                            <option value="unit">{{ line.unit }}</option>
                                            <option v-if="line.pack_unit" value="pack">
                                                {{ line.pack_unit }} (isi {{ line.units_per_pack }})
                                            </option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-3">
                                        <input v-model="line.price" type="number" min="0" step="any" :class="input" />
                                    </td>
                                    <td class="py-3 pr-3 text-right font-medium">{{ formatRupiah(subtotal(line)) }}</td>
                                    <td class="py-3 text-right">
                                        <button type="button" class="text-red-600 hover:underline" title="Hapus baris" @click="removeLine(i)">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="lineErrors(i).length">
                                    <td colspan="6" class="pb-3 text-sm text-red-600">{{ lineErrors(i).join(' ') }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-md bg-gray-50 px-3 py-6 text-center text-sm text-gray-500">
                    Belum ada barang. Cari dan pilih barang di atas.
                </p>
            </div>

            <div class="flex flex-col items-end gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-blue-900">Total biaya: {{ formatRupiah(total) }}</p>
                <div class="flex items-center gap-3">
                    <Link :href="route('stock-ins.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.items.length"
                        class="rounded-md bg-blue-900 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                    >
                        Simpan Barang Masuk
                    </button>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
