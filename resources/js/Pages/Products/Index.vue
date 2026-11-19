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
let timer = null;

watch([search, category], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('products.index'),
            { search: search.value || undefined, category: category.value || undefined },
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
            <h2 class="text-lg font-semibold text-blue-900">Data Barang</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                    <input v-model="search" type="search" placeholder="Cari nama atau kode barang…" :class="[field, 'w-full sm:max-w-xs']" />
                    <select v-model="category" :class="[field, 'w-full sm:w-52']">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <Link
                    v-if="can.manage"
                    :href="route('products.create')"
                    class="rounded-md bg-blue-900 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-800"
                >
                    + Tambah Barang
                </Link>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3 text-right">Stok</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Harga jual</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!products.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                {{ filters.search || filters.category ? 'Tidak ada barang yang cocok.' : 'Belum ada barang.' }}
                            </td>
                        </tr>
                        <tr v-for="p in products.data" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="p.photo_url" :src="p.photo_url" :alt="p.name" class="h-10 w-10 rounded-lg border object-cover" />
                                    <div v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 font-semibold text-blue-900">
                                        {{ p.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link :href="route('products.show', p.id)" class="block truncate font-medium text-gray-900 hover:text-blue-800">
                                            {{ p.name }}
                                        </Link>
                                        <p class="text-xs text-gray-500">{{ p.code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ p.category ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ formatNumber(p.stock) }} {{ p.unit }}</td>
                            <td class="px-4 py-3"><StockBadge :status="p.status" /></td>
                            <td class="px-4 py-3 text-right">{{ formatRupiah(p.selling_price) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="route('products.show', p.id)" class="text-blue-800 hover:underline">Detail</Link>
                                    <template v-if="can.manage">
                                        <Link :href="route('products.edit', p.id)" class="text-blue-800 hover:underline">Ubah</Link>
                                        <button type="button" class="text-red-600 hover:underline" @click="deleting = p">Hapus</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
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
