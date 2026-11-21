<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({ categories: Object, filters: Object });

const search = ref(props.filters.search ?? '');
let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('categories.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

// Modal tambah / ubah
const formOpen = ref(false);
const editing = ref(null);
const form = useForm({ name: '', description: '' });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(c) {
    editing.value = c;
    form.name = c.name;
    form.description = c.description ?? '';
    form.clearErrors();
    formOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    };
    if (editing.value) form.put(route('categories.update', editing.value.id), options);
    else form.post(route('categories.store'), options);
}

// Hapus
const deleting = ref(null);
const processing = ref(false);

function confirmDelete() {
    router.delete(route('categories.destroy', deleting.value.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            deleting.value = null;
        },
    });
}

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Kategori" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Kategori Barang</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Master Data
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari kategori…"
                    class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:max-w-xs"
                />
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-2.5 text-center text-sm font-bold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98]"
                    @click="openCreate"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Kategori
                </button>
            </div>

            <div class="modern-table-box">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Nama Kategori</th>
                            <th class="px-5 py-3.5">Deskripsi</th>
                            <th class="px-5 py-3.5 text-right">Jumlah Barang</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!categories.data.length">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-400">
                                {{ filters.search ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Belum ada data kategori.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="c in categories.data"
                            :key="c.id"
                            class="group table-row-modern"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 font-bold text-xs ring-1 ring-blue-700/10">
                                        #
                                    </div>
                                    <span class="font-bold text-slate-800 group-hover:text-blue-700 transition-colors">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 text-xs">{{ c.description || '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ formatNumber(c.products_count) }} produk
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-blue-200 bg-blue-50/50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100/60 transition-colors"
                                        @click="openEdit(c)"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100/60 transition-colors"
                                        @click="deleting = c"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="categories.links" :from="categories.from ?? 0" :to="categories.to ?? 0" :total="categories.total" />
        </div>

        <Modal :show="formOpen" max-width="md" @close="formOpen = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-semibold text-gray-900">{{ editing ? 'Ubah Kategori' : 'Tambah Kategori' }}</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="cat-name" class="block text-sm font-medium text-gray-700">Nama kategori</label>
                        <input id="cat-name" v-model="form.name" type="text" :class="input" required autofocus />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <label for="cat-desc" class="block text-sm font-medium text-gray-700">Deskripsi (opsional)</label>
                        <textarea id="cat-desc" v-model="form.description" rows="3" :class="input" />
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="formOpen = false">
                        Batal
                    </button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50">
                        Simpan
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleting"
            title="Hapus kategori?"
            :message="
                deleting
                    ? `Kategori “${deleting.name}” akan dihapus.` +
                      (deleting.products_count
                          ? ` ${deleting.products_count} barang di dalamnya akan menjadi “tanpa kategori”.`
                          : '')
                    : ''
            "
            :processing="processing"
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
