<script setup>
const fitur = [
    'Pantau stok barang secara real-time',
    'Catat barang masuk, keluar, dan penjualan',
    'Asisten AI untuk rekomendasi pengadaan',
];
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <aside class="hidden flex-col justify-between bg-blue-900 p-12 text-white lg:flex lg:w-1/2">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                </div>
                <span class="text-xl font-semibold tracking-wide">STOKARA</span>
            </div>

            <div>
                <h2 class="text-3xl font-bold leading-tight">Kelola Stok, Kendalikan Bisnis.</h2>
                <ul class="mt-8 space-y-4 text-blue-100">
                    <li v-for="item in fitur" :key="item" class="flex items-start gap-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-green-400"></span>
                        {{ item }}
                    </li>
                </ul>
            </div>

            <p class="text-sm text-blue-200">Inventory Management · POS · Business Intelligence · AI Assistant</p>
        </aside>

        <main class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="w-full max-w-md">
                <slot />
            </div>
        </main>
    </div>
</template>
