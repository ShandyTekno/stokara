<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputError from '@/Components/InputError.vue';
import { applyTheme } from '@/composables/useTheme';

const props = defineProps({
    store: Object,
    options: Object,
});

const page = usePage();
const activeTab = ref('identity');
const logoPreview = ref(props.store.logo_url || null);
const showResetModal = ref(false);
const resetting = ref(false);

const form = useForm({
    app_name: props.store.app_name ?? 'STOKARA',
    name: props.store.name ?? '',
    tagline: props.store.tagline ?? '',
    phone: props.store.phone ?? '',
    address: props.store.address ?? '',
    logo: null,
    remove_logo: false,
    theme_color: props.store.settings?.theme_color ?? 'blue',
    sidebar_style: props.store.settings?.sidebar_style ?? 'dark',
    font_family: props.store.settings?.font_family ?? 'figtree',
    font_size: props.store.settings?.font_size ?? 'md',
    border_radius: props.store.settings?.border_radius ?? 'rounded',
    compact_mode: Boolean(props.store.settings?.compact_mode ?? false),
    receipt_footer: props.store.settings?.receipt_footer ?? '',
});

// Live preview immediately as admin tweaks appearance controls
watch(
    () => ({
        theme_color: form.theme_color,
        sidebar_style: form.sidebar_style,
        font_family: form.font_family,
        font_size: form.font_size,
        border_radius: form.border_radius,
        compact_mode: form.compact_mode,
    }),
    (previewSettings) => {
        applyTheme(previewSettings);
    },
    { deep: true },
);

// Revert to saved settings if leaving without saving
onBeforeUnmount(() => {
    applyTheme(page.props.site?.settings);
});

const colorSwatches = {
    blue: { bg: '#2563eb', soft: '#eff6ff', ring: '#93c5fd', desc: 'Klasik & profesional' },
    emerald: { bg: '#059669', soft: '#ecfdf5', ring: '#6ee7b7', desc: 'Segar & alami' },
    indigo: { bg: '#4f46e5', soft: '#eef2ff', ring: '#a5b4fc', desc: 'Modern & dinamis' },
    rose: { bg: '#e11d48', soft: '#fff1f2', ring: '#fda4af', desc: 'Hangat & berani' },
    amber: { bg: '#d97706', soft: '#fffbeb', ring: '#fcd34d', desc: 'Cerah & energik' },
    violet: { bg: '#7c3aed', soft: '#f5f3ff', ring: '#c4b5fd', desc: 'Kreatif & elegan' },
    slate: { bg: '#334155', soft: '#f8fafc', ring: '#cbd5e1', desc: 'Minimalis & monokrom' },
};

const fontCssMap = {
    figtree: "'Figtree', sans-serif",
    inter: "'Inter', sans-serif",
    'plus-jakarta-sans': "'Plus Jakarta Sans', sans-serif",
    poppins: "'Poppins', sans-serif",
    nunito: "'Nunito', sans-serif",
    roboto: "'Roboto', sans-serif",
};

const tabs = [
    { id: 'identity', label: 'Identitas Website', desc: 'Nama, logo, info toko & struk' },
    { id: 'appearance', label: 'Warna & Tampilan', desc: 'Tema warna, sidebar & sudut' },
    { id: 'typography', label: 'Font & Tipografi', desc: 'Jenis huruf & ukuran teks' },
];

const hasUnsavedAppearance = computed(() => {
    const saved = props.store.settings ?? {};
    return (
        form.theme_color !== (saved.theme_color ?? 'blue') ||
        form.sidebar_style !== (saved.sidebar_style ?? 'dark') ||
        form.font_family !== (saved.font_family ?? 'figtree') ||
        form.font_size !== (saved.font_size ?? 'md') ||
        form.border_radius !== (saved.border_radius ?? 'rounded') ||
        form.compact_mode !== Boolean(saved.compact_mode ?? false)
    );
});

function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    if (!file) {
        return;
    }
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function submit() {
    form.post(route('settings.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            form.remove_logo = false;
            if (form.app_name) {
                document.title = `Pengaturan Website - ${form.app_name}`;
            }
        },
    });
}

function confirmReset() {
    router.post(
        route('settings.reset'),
        {},
        {
            preserveScroll: true,
            onStart: () => (resetting.value = true),
            onSuccess: () => {
                form.theme_color = 'blue';
                form.sidebar_style = 'dark';
                form.font_family = 'figtree';
                form.font_size = 'md';
                form.border_radius = 'rounded';
                form.compact_mode = false;
                form.receipt_footer = 'Terima kasih telah berbelanja!';
            },
            onFinish: () => {
                resetting.value = false;
                showResetModal.value = false;
            },
        },
    );
}

const inputClass =
    'mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-xs transition-colors focus:border-blue-600 focus:ring-blue-600';
const labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-600';
</script>

<template>
    <Head title="Pengaturan Website" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Pengaturan Website</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Kustomisasi
                </span>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <!-- Live preview notice -->
            <div
                v-if="hasUnsavedAppearance"
                class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/90 p-4 text-sm text-amber-900 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <span class="flex h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse" />
                    <span class="font-medium">
                        Mode Pratinjau Langsung aktif — klik <strong>Simpan Pengaturan</strong> untuk menerapkan perubahan secara permanen.
                    </span>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Simpan Sekarang
                </button>
            </div>

            <!-- Navigation Tabs -->
            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    :class="[
                        'flex flex-col items-start rounded-2xl border p-4 text-left transition-all duration-200',
                        activeTab === tab.id
                            ? 'border-blue-600 bg-blue-50/70 shadow-sm ring-1 ring-blue-600/20'
                            : 'border-slate-200/80 bg-white hover:border-slate-300 hover:bg-slate-50/60',
                    ]"
                    @click="activeTab = tab.id"
                >
                    <span
                        :class="[
                            'text-sm font-bold',
                            activeTab === tab.id ? 'text-blue-700' : 'text-slate-800',
                        ]"
                    >
                        {{ tab.label }}
                    </span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ tab.desc }}</span>
                </button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- TAB 1: IDENTITAS WEBSITE & TOKO -->
                <div v-show="activeTab === 'identity'" class="space-y-6">
                    <!-- Brand & Logo Card -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Identitas & Branding Website</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Ubah nama aplikasi, logo, dan tagline yang tampil di sidebar, halaman login, serta judul tab browser.
                            </p>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <!-- Logo Upload -->
                            <div class="md:col-span-2">
                                <label :class="labelClass">Logo Website</label>
                                <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center">
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-2 shadow-xs">
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="Preview Logo"
                                            class="h-full w-full object-contain"
                                        />
                                        <svg v-else class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                        </svg>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label
                                                class="cursor-pointer inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50"
                                            >
                                                <span>Pilih Logo</span>
                                                <input
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                                    class="hidden"
                                                    @change="onLogoChange"
                                                />
                                            </label>
                                            <button
                                                v-if="logoPreview"
                                                type="button"
                                                class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                                @click="removeLogo"
                                            >
                                                Hapus Logo
                                            </button>
                                        </div>
                                        <p class="text-xs text-slate-500">
                                            Format JPG, PNG, WEBP, atau SVG. Maksimal 2 MB. Disarankan rasio persegi (1:1).
                                        </p>
                                        <InputError :message="form.errors.logo" />
                                    </div>
                                </div>
                            </div>

                            <!-- Nama Website -->
                            <div>
                                <label for="app_name" :class="labelClass">Nama Website / Aplikasi</label>
                                <input
                                    id="app_name"
                                    v-model="form.app_name"
                                    type="text"
                                    required
                                    placeholder="Misal: STOKARA"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.app_name" class="mt-1" />
                            </div>

                            <!-- Nama Toko -->
                            <div>
                                <label for="name" :class="labelClass">Nama Toko</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Misal: Toko Sembako Berkah"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.name" class="mt-1" />
                            </div>

                            <!-- Tagline -->
                            <div class="md:col-span-2">
                                <label for="tagline" :class="labelClass">Tagline / Deskripsi Singkat</label>
                                <input
                                    id="tagline"
                                    v-model="form.tagline"
                                    type="text"
                                    placeholder="Misal: Sistem Manajemen Stok & Kasir Modern"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.tagline" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <!-- Store Contact & Receipt Card -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Informasi Toko & Struk</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Informasi kontak dan catatan yang ditampilkan pada profil toko dan struk transaksi.
                            </p>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div>
                                <label for="phone" :class="labelClass">Nomor Telepon / WhatsApp</label>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="08xxxxxxxxxx"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.phone" class="mt-1" />
                            </div>

                            <div>
                                <label for="receipt_footer" :class="labelClass">Catatan Kaki Struk</label>
                                <input
                                    id="receipt_footer"
                                    v-model="form.receipt_footer"
                                    type="text"
                                    placeholder="Terima kasih telah berbelanja!"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.receipt_footer" class="mt-1" />
                            </div>

                            <div class="md:col-span-2">
                                <label for="address" :class="labelClass">Alamat Toko</label>
                                <textarea
                                    id="address"
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Jl. Contoh No. 123, Kota..."
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.address" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: WARNA & TAMPILAN -->
                <div v-show="activeTab === 'appearance'" class="space-y-6">
                    <!-- Color Theme Picker -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Palet Warna Tema</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Pilih warna utama untuk tombol, aksen, sidebar, dan elemen interaktif di seluruh halaman.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <button
                                v-for="(label, key) in options.theme_colors"
                                :key="key"
                                type="button"
                                :class="[
                                    'group relative flex items-center gap-3 rounded-xl border p-3.5 text-left transition-all duration-200',
                                    form.theme_color === key
                                        ? 'border-slate-900 bg-slate-50 shadow-sm ring-2 ring-slate-900/10'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50',
                                ]"
                                @click="form.theme_color = key"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition-transform group-hover:scale-105"
                                    :style="{ backgroundColor: colorSwatches[key]?.bg || '#2563eb' }"
                                >
                                    <svg
                                        v-if="form.theme_color === key"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ label }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ colorSwatches[key]?.desc }}</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar Style Picker -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Gaya Sidebar Navigasi</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Sesuaikan nuansa menu samping agar paling nyaman dipandang saat bekerja.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <button
                                v-for="(label, key) in options.sidebar_styles"
                                :key="key"
                                type="button"
                                :class="[
                                    'flex flex-col overflow-hidden rounded-xl border text-left transition-all duration-200',
                                    form.sidebar_style === key
                                        ? 'border-blue-600 ring-2 ring-blue-600/20 shadow-sm'
                                        : 'border-slate-200 hover:border-slate-300',
                                ]"
                                @click="form.sidebar_style = key"
                            >
                                <!-- Mini visual mockup -->
                                <div class="flex h-20 w-full bg-slate-100 p-2 gap-2">
                                    <div
                                        :class="[
                                            'w-1/3 rounded-lg p-1.5 space-y-1',
                                            key === 'dark'
                                                ? 'bg-slate-900'
                                                : key === 'colored'
                                                    ? ''
                                                    : 'bg-white border border-slate-200',
                                        ]"
                                        :style="key === 'colored' ? { backgroundColor: colorSwatches[form.theme_color]?.bg } : {}"
                                    >
                                        <div
                                            :class="[
                                                'h-1.5 w-3/4 rounded-full',
                                                key === 'light' ? 'bg-slate-300' : 'bg-white/40',
                                            ]"
                                        />
                                        <div
                                            :class="[
                                                'h-1.5 w-full rounded-full',
                                                key === 'light' ? 'bg-blue-200' : 'bg-white/75',
                                            ]"
                                        />
                                        <div
                                            :class="[
                                                'h-1.5 w-2/3 rounded-full',
                                                key === 'light' ? 'bg-slate-200' : 'bg-white/25',
                                            ]"
                                        />
                                    </div>
                                    <div class="flex-1 rounded-lg bg-white p-1.5 space-y-1.5 border border-slate-200/60">
                                        <div class="h-2 w-1/2 rounded-full bg-slate-200" />
                                        <div class="h-8 w-full rounded-md bg-slate-50 border border-slate-100" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between bg-white px-3.5 py-2.5">
                                    <span class="text-sm font-semibold text-slate-800">{{ label }}</span>
                                    <span
                                        v-if="form.sidebar_style === key"
                                        class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700"
                                    >
                                        Aktif
                                    </span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Border Radius & Compact Mode -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                            <h3 class="text-base font-bold text-slate-900">Bentuk Sudut Kartu & Tombol</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Atur tingkat kelengkungan elemen antarmuka.</p>

                            <div class="mt-4 space-y-2.5">
                                <button
                                    v-for="(label, key) in options.border_radii"
                                    :key="key"
                                    type="button"
                                    :class="[
                                        'flex w-full items-center justify-between border px-4 py-3 text-sm font-medium transition-all',
                                        key === 'sharp' ? 'rounded-md' : key === 'pill' ? 'rounded-2xl' : 'rounded-xl',
                                        form.border_radius === key
                                            ? 'border-blue-600 bg-blue-50/70 text-blue-700 font-semibold'
                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                    ]"
                                    @click="form.border_radius = key"
                                >
                                    <span>{{ label }}</span>
                                    <span
                                        :class="[
                                            'h-4 w-4 border-2',
                                            key === 'sharp' ? 'rounded-xs' : key === 'pill' ? 'rounded-full' : 'rounded-md',
                                            form.border_radius === key ? 'border-blue-600 bg-blue-600' : 'border-slate-300',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Mode Tampilan Padat (Compact)</h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Mengurangi jarak spasi pada tabel dan konten agar lebih banyak data terlihat dalam satu layar. Cocok untuk layar laptop kecil atau kasir.
                                </p>
                            </div>

                            <div class="mt-6 flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50 p-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Aktifkan Mode Padat</p>
                                    <p class="text-xs text-slate-500">
                                        {{ form.compact_mode ? 'Aktif — spasi lebih ringkas' : 'Nonaktif — spasi lega & nyaman' }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.compact_mode"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                                        form.compact_mode ? 'bg-blue-600' : 'bg-slate-300',
                                    ]"
                                    @click="form.compact_mode = !form.compact_mode"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out',
                                            form.compact_mode ? 'translate-x-5' : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: TIPOGRAFI (FONT & UKURAN) -->
                <div v-show="activeTab === 'typography'" class="space-y-6">
                    <!-- Font Family -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Jenis Huruf (Font Family)</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Pilih font yang paling nyaman dibaca untuk seluruh teks, angka, dan tabel pada website.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="(label, key) in options.font_families"
                                :key="key"
                                type="button"
                                :style="{ fontFamily: fontCssMap[key] }"
                                :class="[
                                    'flex flex-col justify-between rounded-xl border p-4 text-left transition-all duration-200',
                                    form.font_family === key
                                        ? 'border-blue-600 bg-blue-50/70 shadow-sm ring-2 ring-blue-600/15'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60',
                                ]"
                                @click="form.font_family = key"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-slate-900">{{ label }}</span>
                                    <span
                                        v-if="form.font_family === key"
                                        class="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                    >
                                        Dipilih
                                    </span>
                                </div>
                                <p class="mt-3 text-2xl font-bold tracking-tight text-slate-800">Aa Bb 123</p>
                                <p class="mt-1 text-xs text-slate-500">Beras Medium · Rp16.500/kg</p>
                            </button>
                        </div>
                    </div>

                    <!-- Font Size -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Ukuran Teks</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Sesuaikan skala ukuran huruf di seluruh aplikasi.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <button
                                v-for="(label, key) in options.font_sizes"
                                :key="key"
                                type="button"
                                :class="[
                                    'flex flex-col items-start rounded-xl border p-4 text-left transition-all',
                                    form.font_size === key
                                        ? 'border-blue-600 bg-blue-50/70 shadow-sm ring-2 ring-blue-600/15'
                                        : 'border-slate-200 bg-white hover:border-slate-300',
                                ]"
                                @click="form.font_size = key"
                            >
                                <span
                                    :class="[
                                        'font-bold text-slate-900',
                                        key === 'sm' ? 'text-sm' : key === 'lg' ? 'text-xl' : 'text-base',
                                    ]"
                                >
                                    Aa
                                </span>
                                <span class="mt-1.5 text-sm font-semibold text-slate-800">{{ label }}</span>
                                <span class="mt-0.5 text-xs text-slate-500">
                                    {{ key === 'sm' ? 'Lebih banyak informasi di layar' : key === 'lg' ? 'Lebih mudah dibaca dari jauh' : 'Seimbang & direkomendasikan' }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sticky Action Footer -->
                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                        @click="showResetModal = true"
                    >
                        Reset Tampilan ke Default
                    </button>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98] disabled:opacity-60 sm:w-auto"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <span>{{ form.processing ? 'Menyimpan…' : 'Simpan Pengaturan' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <ConfirmModal
            :show="showResetModal"
            title="Reset tampilan ke default?"
            message="Warna tema, gaya sidebar, jenis font, ukuran teks, dan bentuk sudut akan dikembalikan ke pengaturan awal. Identitas toko & logo tidak akan dihapus."
            :processing="resetting"
            @close="showResetModal = false"
            @confirm="confirmReset"
        />
    </AuthenticatedLayout>
</template>
