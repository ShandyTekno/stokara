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
            <h2 class="text-lg font-semibold text-blue-900">Kategori</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input v-model="search" type="search" placeholder="Cari kategori…" :class="[input, 'sm:max-w-xs']" />
                <button type="button" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" @click="openCreate">
                    + Tambah Kategori
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Deskripsi</th>
                            <th class="px-4 py-3 text-right">Jumlah barang</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!categories.data.length">
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                                {{ filters.search ? 'Tidak ada kategori yang cocok.' : 'Belum ada kategori.' }}
                            </td>
                        </tr>
                        <tr v-for="c in categories.data" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ c.name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ c.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ formatNumber(c.products_count) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="text-blue-800 hover:underline" @click="openEdit(c)">Ubah</button>
                                    <button type="button" class="text-red-600 hover:underline" @click="deleting = c">Hapus</button>
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
