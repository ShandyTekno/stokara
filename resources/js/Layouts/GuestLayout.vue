<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

useTheme();

const page = usePage();
const site = computed(() => page.props.site ?? {});
const logoFailed = ref(false);

const fitur = [
    { title: 'Pantau Stok Real-time', desc: 'Notifikasi otomatis saat stok barang menipis atau habis' },
    { title: 'Kasir & Transaksi Cepat', desc: 'Catat penjualan tunai/QRIS, barang masuk, dan barang keluar' },
    { title: 'Kecerdasan Buatan (AI)', desc: 'Rekomendasi pengadaan & analisis tren penjualan berbasis Gemini' },
];
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 antialiased">
        <!-- Left Branding Panel -->
        <aside class="guest-brand-panel relative hidden flex-col justify-between overflow-hidden p-12 text-white lg:flex lg:w-1/2">
            <!-- Decorative Glow Circles -->
            <div class="pointer-events-none absolute -left-20 -top-20 h-72 w-72 rounded-full bg-blue-600/20 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-20 -right-20 h-80 w-80 rounded-full bg-indigo-600/20 blur-3xl" />

            <!-- Brand Logo -->
            <div class="relative z-10 flex items-center gap-3">
                <div
                    v-if="site.logo_url && !logoFailed"
                    class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-white p-1.5 shadow-md"
                >
                    <img
                        :src="site.logo_url"
                        :alt="site.app_name"
                        class="h-full w-full object-contain"
                        @error="logoFailed = true"
                    />
                </div>
                <div
                    v-else
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 text-blue-300 shadow-md"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-wider text-white">{{ site.app_name || 'STOKARA' }}</span>
                    <p class="text-[11px] text-blue-300">{{ site.tagline || 'Sistem Manajemen Stok & Kasir Modern' }}</p>
                </div>
            </div>

            <!-- Value Proposition -->
            <div class="relative z-10 my-auto py-8">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-blue-300 ring-1 ring-white/15 mb-4">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                    Smart Inventory Platform
                </span>
                <h2 class="text-3xl font-extrabold leading-tight text-white lg:text-4xl">
                    Kelola Stok Cerdas,<br />Kendalikan Bisnis Anda.
                </h2>
                <p class="mt-4 text-sm text-slate-300 max-w-md">
                    Otomatisasi pencatatan inventaris dan penjualan sembako/toko dengan bantuan analisis AI yang praktis dan efisien.
                </p>

                <div class="mt-8 space-y-4">
                    <div
                        v-for="item in fitur"
                        :key="item.title"
                        class="flex items-start gap-3.5 rounded-xl border border-white/5 bg-white/5 p-3.5 backdrop-blur-xs transition-all hover:bg-white/10 hover:translate-x-1"
                    >
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ item.title }}</p>
                            <p class="text-xs text-slate-300/80">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <p class="relative z-10 text-xs text-slate-400">
                &copy; {{ new Date().getFullYear() }} {{ site.app_name || 'STOKARA' }} · Inventory Management · POS · AI Assistant
            </p>
        </aside>

        <!-- Right Content / Form Container -->
        <main class="flex w-full items-center justify-center p-6 sm:p-12 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-[0_10px_35px_-5px_rgba(15,23,42,0.08)] sm:p-10">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
