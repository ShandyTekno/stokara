<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StockBadge from '@/Components/StockBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({
    products: Object,
    categories: Array,
    filters: Object,
    can: Object,
});

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const perPage = ref(props.filters.per_page ?? 10);
const failedImages = ref({});
let timer = null;

watch([search, category, perPage], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('products.index'),
            {
                search: search.value || undefined,
                category: category.value || undefined,
                per_page: perPage.value !== 10 ? perPage.value : undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

const deleting = ref(null);
const processing = ref(false);

function confirmDelete() {
    router.delete(route('products.destroy', deleting.value.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            deleting.value = null;
        },
    });
}

const field =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Data Barang" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Data Barang</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Katalog
                </span>
            </div>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <div class="relative w-full sm:max-w-xs">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari nama atau kode barang…"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600"
                        />
                    </div>
                    <select
                        v-model="category"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:w-60"
                    >
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select
                        v-model="perPage"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:w-32"
                    >
                        <option :value="10">10 / hal</option>
                        <option :value="25">25 / hal</option>
                        <option :value="50">50 / hal</option>
                        <option :value="100">100 / hal</option>
                    </select>
                </div>
                <Link
                    v-if="can.manage"
                    :href="route('products.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Barang
                </Link>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-xs">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Barang</th>
                            <th class="px-5 py-3.5">Kategori</th>
                            <th class="px-5 py-3.5 text-right">Stok</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Harga Jual</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!products.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                {{ filters.search || filters.category ? 'Tidak ada barang yang cocok dengan filter.' : 'Belum ada data barang tersimpan.' }}
                            </td>
                        </tr>
                        <template
                            v-for="(p, index) in products.data"
                            :key="p.id"
                        >
                            <!-- Header pemisah kelompok kategori -->
                            <tr
                                v-if="!category && (index === 0 || p.category !== products.data[index - 1].category)"
                                class="bg-gradient-to-r from-slate-100/90 via-slate-50/70 to-white border-y border-slate-200/80"
                            >
                                <td colspan="6" class="px-5 py-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25A2.25 2.25 0 018.25 10.5H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                                {{ p.category || 'Tanpa Kategori' }}
                                            </span>
                                        </div>
                                        <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 border border-slate-200/80 shadow-xs">
                                            Kelompok Kategori
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Baris Data Barang -->
                            <tr
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="p.photo_url && !failedImages[p.id]"
                                            :src="p.photo_url"
                                            :alt="p.name"
                                            class="h-10 w-10 rounded-xl border border-slate-200 object-cover shadow-xs"
                                            @error="failedImages[p.id] = true"
                                        />
                                        <div
                                            v-else
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 font-bold text-blue-700 ring-1 ring-blue-700/10 shadow-xs"
                                        >
                                            {{ p.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <Link
                                                :href="route('products.show', p.id)"
                                                class="block truncate font-semibold text-slate-800 hover:text-blue-700 transition-colors"
                                            >
                                                {{ p.name }}
                                            </Link>
                                            <p class="text-xs text-slate-400 font-mono">{{ p.code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ p.category ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-800">
                                    {{ formatNumber(p.stock) }} <span class="text-xs font-normal text-slate-400">{{ p.unit }}</span>
                                </td>
                                <td class="px-5 py-3.5"><StockBadge :status="p.status" /></td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-900">{{ formatRupiah(p.selling_price) }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex justify-end items-center gap-2">
                                        <Link
                                            :href="route('products.show', p.id)"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                                        >
                                            Detail
                                        </Link>
                                        <template v-if="can.manage">
                                            <Link
                                                :href="route('products.edit', p.id)"
                                                class="rounded-lg border border-blue-200 bg-blue-50/50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100/60 transition-colors"
                                            >
                                                Ubah
                                            </Link>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100/60 transition-colors"
                                                @click="deleting = p"
                                            >
                                                Hapus
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <Pagination :links="products.links" :from="products.from ?? 0" :to="products.to ?? 0" :total="products.total" />
        </div>

        <ConfirmModal
            :show="!!deleting"
            title="Hapus barang?"
            :message="deleting ? `Barang “${deleting.name}” akan dihapus. Riwayat transaksinya tetap tersimpan.` : ''"
            :processing="processing"
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
