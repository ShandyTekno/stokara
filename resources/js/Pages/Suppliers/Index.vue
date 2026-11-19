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
            <h2 class="text-lg font-semibold text-blue-900">Pemasok</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari nama, perusahaan, atau telepon…"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800 sm:max-w-xs"
                />
                <Link :href="route('suppliers.create')" class="rounded-md bg-blue-900 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-800">
                    + Tambah Pemasok
                </Link>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Pemasok</th>
                            <th class="px-4 py-3">Perusahaan</th>
                            <th class="px-4 py-3">Telepon</th>
                            <th class="px-4 py-3 text-right">Jumlah barang</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!suppliers.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                {{ filters.search ? 'Tidak ada pemasok yang cocok.' : 'Belum ada pemasok.' }}
                            </td>
                        </tr>
                        <tr v-for="s in suppliers.data" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link :href="route('suppliers.show', s.id)" class="font-medium text-gray-900 hover:text-blue-800">{{ s.name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ s.company_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ s.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ formatNumber(s.products_count) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="route('suppliers.show', s.id)" class="text-blue-800 hover:underline">Detail</Link>
                                    <Link :href="route('suppliers.edit', s.id)" class="text-blue-800 hover:underline">Ubah</Link>
                                    <button type="button" class="text-red-600 hover:underline" @click="deleting = s">Hapus</button>
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
                      (deleting.products_count ? ` ${deleting.products_count} barangnya akan menjadi “tanpa pemasok”.` : '') +
                      ' Pemasok yang punya riwayat pengadaan tidak bisa dihapus.'
                    : ''
            "
            :processing="processing"
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
