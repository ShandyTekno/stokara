<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({ stockIns: Object, summary: Object, filters: Object });

const search = ref(props.filters.search ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
let timer = null;

watch([search, from, to], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('stock-ins.index'),
            { search: search.value || undefined, from: from.value || undefined, to: to.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

function reset() {
    search.value = '';
    from.value = '';
    to.value = '';
}

const field =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Barang Masuk" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Barang Masuk</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div>
                        <label class="block text-xs text-gray-500">Cari</label>
                        <input v-model="search" type="search" placeholder="No. transaksi atau pemasok…" :class="[field, 'w-full sm:w-64']" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Dari tanggal</label>
                        <input v-model="from" type="date" :class="field" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Sampai tanggal</label>
                        <input v-model="to" type="date" :class="field" />
                    </div>
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="reset">
                        Reset
                    </button>
                </div>
                <Link :href="route('stock-ins.create')" class="rounded-md bg-blue-900 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-800">
                    + Catat Barang Masuk
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Jumlah transaksi</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ formatNumber(summary.count) }}</p>
                </div>
                <div class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total biaya</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ formatRupiah(summary.total_cost) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">No. transaksi</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Pemasok</th>
                            <th class="px-4 py-3 text-right">Jenis barang</th>
                            <th class="px-4 py-3 text-right">Total biaya</th>
                            <th class="px-4 py-3">Dicatat oleh</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!stockIns.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                {{ filters.search || filters.from || filters.to ? 'Tidak ada transaksi yang cocok.' : 'Belum ada barang masuk.' }}
                            </td>
                        </tr>
                        <tr v-for="s in stockIns.data" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="route('stock-ins.show', s.id)" class="text-gray-900 hover:text-blue-800">{{ s.transaction_number }}</Link>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ s.date }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ s.supplier ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ formatNumber(s.items) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRupiah(s.total_cost) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ s.user ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('stock-ins.show', s.id)" class="text-blue-800 hover:underline">Detail</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="stockIns.links" :from="stockIns.from ?? 0" :to="stockIns.to ?? 0" :total="stockIns.total" />
        </div>
    </AuthenticatedLayout>
</template>
