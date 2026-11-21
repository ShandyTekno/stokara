<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({ suppliers: Object, filters: Object });

const search = ref(props.filters.search ?? '');
let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('suppliers.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

const deleting = ref(null);
const processing = ref(false);

function confirmDelete() {
    router.delete(route('suppliers.destroy', deleting.value.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Pemasok" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Data Pemasok</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Vendor
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari nama, perusahaan, atau telepon…"
                    class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:max-w-xs"
                />
                <Link
                    :href="route('suppliers.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-2.5 text-center text-sm font-bold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Pemasok
                </Link>
            </div>

            <div class="modern-table-box">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Nama Kontak</th>
                            <th class="px-5 py-3.5">Perusahaan</th>
                            <th class="px-5 py-3.5">Telepon</th>
                            <th class="px-5 py-3.5 text-right">Jumlah Barang</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!suppliers.data.length">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                                {{ filters.search ? 'Tidak ada pemasok yang cocok dengan pencarian.' : 'Belum ada data pemasok tersimpan.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="s in suppliers.data"
                            :key="s.id"
                            class="group table-row-modern"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-700 font-bold text-xs ring-1 ring-blue-700/10">
                                        {{ s.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <Link
                                        :href="route('suppliers.show', s.id)"
                                        class="font-semibold text-slate-800 hover:text-blue-700 transition-colors"
                                    >
                                        {{ s.name }}
                                    </Link>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium">{{ s.company_name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-500 font-mono text-xs">{{ s.phone ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ formatNumber(s.products_count) }} produk
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    <Link
                                        :href="route('suppliers.show', s.id)"
                                        class="btn-table-action"
                                    >
                                        Detail
                                    </Link>
                                    <Link
                                        :href="route('suppliers.edit', s.id)"
                                        class="rounded-lg border border-blue-200 bg-blue-50/50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100/60 transition-colors"
                                    >
                                        Ubah
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100/60 transition-colors"
                                        @click="deleting = s"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="suppliers.links" :from="suppliers.from ?? 0" :to="suppliers.to ?? 0" :total="suppliers.total" />
        </div>

        <ConfirmModal
            :show="!!deleting"
            title="Hapus pemasok?"
            :message="
                deleting
                    ? `Pemasok “${deleting.name}” akan dihapus.` +
                      (deleting.products_count
                          ? ` ${deleting.products_count} barang yang terhubung tidak akan terhapus, tetapi tidak lagi memiliki pemasok.`
                          : '')
                    : ''
            "
            :processing="processing"
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
