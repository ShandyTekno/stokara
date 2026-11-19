<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    mode: { type: String, default: 'create' },
    currentStock: { type: Number, default: 0 },
    currentPhotoUrl: { type: String, default: null },
    submitLabel: { type: String, default: 'Simpan' },
});

defineEmits(['submit']);

const preview = ref(props.currentPhotoUrl);

function onPhoto(e) {
    const file = e.target.files[0] ?? null;
    props.form.photo = file;
    if (file) {
        preview.value = URL.createObjectURL(file);
        props.form.remove_photo = false;
    }
}

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
const label = 'block text-sm font-medium text-gray-700';
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2">
            <div>
                <label for="code" :class="label">Kode barang</label>
                <input id="code" v-model="form.code" type="text" :class="input" required />
                <InputError class="mt-1" :message="form.errors.code" />
            </div>
            <div>
                <label for="name" :class="label">Nama barang</label>
                <input id="name" v-model="form.name" type="text" :class="input" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <label for="category_id" :class="label">Kategori</label>
                <select id="category_id" v-model="form.category_id" :class="input">
                    <option value="">— Tanpa kategori —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.category_id" />
            </div>
            <div>
                <label for="supplier_id" :class="label">Pemasok</label>
                <select id="supplier_id" v-model="form.supplier_id" :class="input">
                    <option value="">— Tanpa pemasok —</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.supplier_id" />
            </div>
            <div>
                <label for="purchase_price" :class="label">Harga beli (per satuan terkecil)</label>
                <input id="purchase_price" v-model="form.purchase_price" type="number" min="0" step="1" :class="input" required />
                <InputError class="mt-1" :message="form.errors.purchase_price" />
            </div>
            <div>
                <label for="selling_price" :class="label">Harga jual (per satuan terkecil)</label>
                <input id="selling_price" v-model="form.selling_price" type="number" min="0" step="1" :class="input" required />
                <InputError class="mt-1" :message="form.errors.selling_price" />
            </div>
        </div>

        <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2">
            <div class="md:col-span-2">
                <h3 class="font-semibold text-blue-900">Satuan dan stok</h3>
                <p class="text-sm text-gray-500">
                    Stok selalu disimpan dalam satuan terkecil. Jika barang dibeli per kemasan, isi satuan kemasan dan
                    isinya agar konversi konsisten.
                </p>
            </div>
            <div>
                <label for="unit" :class="label">Satuan terkecil</label>
                <input id="unit" v-model="form.unit" type="text" placeholder="pcs, kg, butir" :class="input" required />
                <InputError class="mt-1" :message="form.errors.unit" />
            </div>
            <div>
                <label for="min_stock" :class="label">Stok minimum</label>
                <input id="min_stock" v-model="form.min_stock" type="number" min="0" :class="input" required />
                <InputError class="mt-1" :message="form.errors.min_stock" />
            </div>
            <div>
                <label for="pack_unit" :class="label">Satuan kemasan (opsional)</label>
                <input id="pack_unit" v-model="form.pack_unit" type="text" placeholder="dus, karung, tray" :class="input" />
                <InputError class="mt-1" :message="form.errors.pack_unit" />
            </div>
            <div>
                <label for="units_per_pack" :class="label">Isi per kemasan</label>
                <input id="units_per_pack" v-model="form.units_per_pack" type="number" min="1" :class="input" required />
                <p v-if="form.pack_unit" class="mt-1 text-xs text-gray-500">
                    1 {{ form.pack_unit }} = {{ form.units_per_pack || 0 }} {{ form.unit || 'satuan' }}
                </p>
                <InputError class="mt-1" :message="form.errors.units_per_pack" />
            </div>

            <div v-if="mode === 'create'">
                <label for="initial_stock" :class="label">Stok awal</label>
                <input id="initial_stock" v-model="form.initial_stock" type="number" min="0" :class="input" required />
                <InputError class="mt-1" :message="form.errors.initial_stock" />
            </div>
            <div v-else>
                <span :class="label">Stok saat ini</span>
                <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    {{ currentStock }} {{ form.unit }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Ubah stok lewat Barang Masuk atau Barang Keluar.</p>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-blue-900">Foto barang</h3>
            <div class="mt-3 flex items-center gap-4">
                <img v-if="preview && !form.remove_photo" :src="preview" alt="Pratinjau foto" class="h-20 w-20 rounded-lg border object-cover" />
                <div v-else class="flex h-20 w-20 items-center justify-center rounded-lg border bg-gray-50 text-xs text-gray-400">
                    Tanpa foto
                </div>
                <div>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-900 hover:file:bg-blue-100"
                        @change="onPhoto"
                    />
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, atau WEBP. Maksimal 2 MB.</p>
                    <label v-if="mode === 'edit' && currentPhotoUrl && !form.photo" class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.remove_photo" type="checkbox" class="rounded border-gray-300 text-blue-900 focus:ring-blue-800" />
                        Hapus foto saat ini
                    </label>
                </div>
            </div>
            <InputError class="mt-2" :message="form.errors.photo" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('products.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-blue-900 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
