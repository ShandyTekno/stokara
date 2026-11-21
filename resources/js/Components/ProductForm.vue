<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
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
const isManualCode = ref(props.mode === 'edit' && Boolean(props.form.code));
const isGenerating = ref(false);
let codeTimer = null;

async function fetchGeneratedCode(name) {
    const trimmed = (name || '').trim();
    if (!trimmed) return;

    isGenerating.value = true;
    try {
        const url = route('products.generate-code') + '?name=' + encodeURIComponent(trimmed);
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            if (data.code && !isManualCode.value) {
                props.form.code = data.code;
            }
        }
    } catch {
        // silent fail
    } finally {
        isGenerating.value = false;
    }
}

watch(
    () => props.form.name,
    (newName) => {
        if (props.mode === 'create' && !isManualCode.value) {
            clearTimeout(codeTimer);
            codeTimer = setTimeout(() => {
                fetchGeneratedCode(newName);
            }, 300);
        }
    }
);

onBeforeUnmount(() => {
    clearTimeout(codeTimer);
});

function onManualCodeEdit() {
    isManualCode.value = true;
}

function resetToAutoCode() {
    isManualCode.value = false;
    fetchGeneratedCode(props.form.name);
}

function onPhoto(e) {
    const file = e.target.files[0] ?? null;
    props.form.photo = file;
    if (file) {
        preview.value = URL.createObjectURL(file);
        props.form.remove_photo = false;
    }
}

const input =
    'mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-xs transition-colors focus:border-blue-600 focus:ring-blue-600';
const label = 'block text-xs font-semibold uppercase tracking-wider text-slate-600';
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <!-- Informasi Utama Barang -->
        <div class="grid gap-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs md:grid-cols-2">
            <div class="md:col-span-2 border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-900">Informasi Utama Barang</h3>
                <p class="text-xs text-slate-500">Nama dan kode unik identitas barang dalam toko.</p>
            </div>

            <!-- Nama Barang (Diutamakan agar kode langsung terbuat) -->
            <div>
                <label for="name" :class="label">Nama barang</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="Contoh: Beras Bulog 5 kg"
                    :class="input"
                    required
                    autofocus
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <!-- Kode Barang Otomatis -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="code" :class="label">Kode barang</label>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="!isManualCode && form.code"
                            class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 ring-1 ring-blue-700/20"
                        >
                            <span v-if="isGenerating" class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-ping" />
                            <span v-else class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {{ isGenerating ? 'Membuat...' : 'Otomatis' }}
                        </span>
                        <button
                            v-if="isManualCode"
                            type="button"
                            class="text-xs font-semibold text-blue-700 hover:text-blue-900 hover:underline"
                            title="Kembalikan kode otomatis berdasarkan nama"
                            @click="resetToAutoCode"
                        >
                            ↺ Buat Otomatis
                        </button>
                    </div>
                </div>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    placeholder="Contoh: BRS-001"
                    :class="[input, 'font-mono font-semibold uppercase tracking-wider text-blue-900']"
                    required
                    @input="onManualCodeEdit"
                />
                <p class="mt-1 text-xs text-slate-400">
                    Otomatis dibuat dari nama barang (dapat diubah manual jika ada kode/barcode sendiri).
                </p>
                <InputError class="mt-1" :message="form.errors.code" />
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
                <input id="purchase_price" v-model="form.purchase_price" type="number" min="0" step="1" placeholder="0" :class="input" required />
                <InputError class="mt-1" :message="form.errors.purchase_price" />
            </div>

            <div>
                <label for="selling_price" :class="label">Harga jual (per satuan terkecil)</label>
                <input id="selling_price" v-model="form.selling_price" type="number" min="0" step="1" placeholder="0" :class="input" required />
                <InputError class="mt-1" :message="form.errors.selling_price" />
            </div>
        </div>

        <!-- Satuan dan Stok -->
        <div class="grid gap-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs md:grid-cols-2">
            <div class="md:col-span-2 border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-900">Satuan dan Stok Barang</h3>
                <p class="text-xs text-slate-500">
                    Stok selalu dihitung dalam satuan terkecil. Jika barang dibeli per kemasan dus/karung, isi satuan kemasan agar otomatis dikonversi.
                </p>
            </div>

            <div>
                <label for="unit" :class="label">Satuan terkecil</label>
                <input id="unit" v-model="form.unit" type="text" placeholder="pcs, kg, butir, bungkus" :class="input" required />
                <InputError class="mt-1" :message="form.errors.unit" />
            </div>

            <div>
                <label for="min_stock" :class="label">Batas stok minimum</label>
                <input id="min_stock" v-model="form.min_stock" type="number" min="0" placeholder="10" :class="input" required />
                <p class="mt-1 text-xs text-slate-400">Sistem akan memberi peringatan jika stok berada di bawah angka ini.</p>
                <InputError class="mt-1" :message="form.errors.min_stock" />
            </div>

            <div>
                <label for="pack_unit" :class="label">Satuan kemasan (opsional)</label>
                <input id="pack_unit" v-model="form.pack_unit" type="text" placeholder="dus, karung, tray, renceng" :class="input" />
                <InputError class="mt-1" :message="form.errors.pack_unit" />
            </div>

            <div>
                <label for="units_per_pack" :class="label">Isi per kemasan</label>
                <input id="units_per_pack" v-model="form.units_per_pack" type="number" min="1" :class="input" required />
                <p v-if="form.pack_unit" class="mt-1 text-xs text-slate-500 font-medium">
                    1 {{ form.pack_unit }} = {{ form.units_per_pack || 0 }} {{ form.unit || 'satuan' }}
                </p>
                <InputError class="mt-1" :message="form.errors.units_per_pack" />
            </div>

            <div v-if="mode === 'create'">
                <label for="initial_stock" :class="label">Stok awal</label>
                <input id="initial_stock" v-model="form.initial_stock" type="number" min="0" placeholder="0" :class="input" required />
                <p class="mt-1 text-xs text-slate-400">Jumlah stok yang langsung tersedia di toko saat ini.</p>
                <InputError class="mt-1" :message="form.errors.initial_stock" />
            </div>
            <div v-else>
                <span :class="label">Stok saat ini</span>
                <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-800">
                    {{ currentStock }} {{ form.unit }}
                </p>
                <p class="mt-1 text-xs text-slate-400">Perubahan stok dilakukan lewat menu Barang Masuk atau Barang Keluar.</p>
            </div>
        </div>

        <!-- Foto Barang -->
        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
            <h3 class="font-bold text-slate-900 mb-1">Foto Barang</h3>
            <p class="text-xs text-slate-500 mb-4">Opsional. Membantu kasir mengenali produk secara visual.</p>
            <div class="flex items-center gap-4">
                <img v-if="preview && !form.remove_photo" :src="preview" alt="Pratinjau foto" class="h-20 w-20 rounded-2xl border border-slate-200 object-cover shadow-xs" />
                <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400">
                    Tanpa foto
                </div>
                <div>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="block text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 file:transition-colors"
                        @change="onPhoto"
                    />
                    <p class="mt-1 text-xs text-slate-400">Format: JPG, PNG, atau WEBP. Maksimal 2 MB.</p>
                    <label v-if="mode === 'edit' && currentPhotoUrl && !form.photo" class="mt-2 flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input v-model="form.remove_photo" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-600" />
                        <span>Hapus foto saat ini</span>
                    </label>
                </div>
            </div>
            <InputError class="mt-2" :message="form.errors.photo" />
        </div>

        <!-- Tombol Aksi -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <Link :href="route('products.index')" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 active:scale-95 transition-all">
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-2.5 text-sm font-bold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98] disabled:opacity-50"
            >
                {{ form.processing ? 'Menyimpan...' : submitLabel }}
            </button>
        </div>
    </form>
</template>
