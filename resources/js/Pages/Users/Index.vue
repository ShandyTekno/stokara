<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ users: Object, filters: Object });

const search = ref(props.filters.search ?? '');
let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('users.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

const deactivating = ref(null);
const processing = ref(false);

function toggle(user, done) {
    router.patch(route('users.toggle', user.id), {}, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            done?.();
        },
    });
}
</script>

<template>
    <Head title="Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Manajemen Pengguna</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari nama atau email…"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800 sm:max-w-xs"
                />
                <Link :href="route('users.create')" class="rounded-md bg-blue-900 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-800">
                    + Tambah Karyawan
                </Link>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Dibuat</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!users.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada pengguna yang cocok.</td>
                        </tr>
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ u.name }}
                                <span v-if="u.is_self" class="ml-1 rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-900">Anda</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ u.email }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ u.role_label }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700',
                                    ]"
                                >
                                    {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ u.created_at }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="route('activities.index', { user: u.id })" class="text-blue-800 hover:underline">Aktivitas</Link>
                                    <template v-if="u.role === 'karyawan'">
                                        <Link :href="route('users.edit', u.id)" class="text-blue-800 hover:underline">Ubah</Link>
                                        <button v-if="u.is_active" type="button" class="text-red-600 hover:underline" @click="deactivating = u">Nonaktifkan</button>
                                        <button v-else type="button" class="text-green-700 hover:underline" @click="toggle(u)">Aktifkan</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="users.links" :from="users.from ?? 0" :to="users.to ?? 0" :total="users.total" />
        </div>

        <ConfirmModal
            :show="!!deactivating"
            title="Nonaktifkan akun?"
            :message="deactivating ? `${deactivating.name} tidak akan bisa masuk lagi dan akan langsung keluar dari semua perangkat. Riwayat transaksinya tetap tersimpan.` : ''"
            confirm-text="Nonaktifkan"
            :processing="processing"
            @close="deactivating = null"
            @confirm="toggle(deactivating, () => (deactivating = null))"
        />
    </AuthenticatedLayout>
</template>
