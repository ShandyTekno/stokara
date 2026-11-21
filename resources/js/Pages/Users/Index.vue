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
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Manajemen Pengguna</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Akses Toko
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari nama atau email…"
                    class="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600 sm:max-w-xs"
                />
                <Link
                    :href="route('users.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-2.5 text-center text-sm font-bold text-white shadow-sm shadow-blue-500/20 transition-all hover:from-blue-800 hover:to-indigo-800 active:scale-[0.98]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Karyawan
                </Link>
            </div>

            <div class="modern-table-box">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Nama & Profil</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Hak Akses (Role)</th>
                            <th class="px-5 py-3.5">Status Akun</th>
                            <th class="px-5 py-3.5">Terdaftar</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!users.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                Tidak ada data pengguna yang cocok.
                            </td>
                        </tr>
                        <tr
                            v-for="u in users.data"
                            :key="u.id"
                            class="group table-row-modern"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-xs font-bold text-white shadow-sm">
                                        {{ u.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 flex items-center gap-1.5">
                                            {{ u.name }}
                                            <span v-if="u.is_self" class="rounded-md bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 ring-1 ring-blue-700/20">
                                                Anda
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 text-xs font-mono">{{ u.email }}</td>
                            <td class="px-5 py-3.5">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-lg px-2.5 py-0.5 text-xs font-semibold',
                                        u.role === 'admin'
                                            ? 'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20'
                                            : 'bg-slate-100 text-slate-700',
                                    ]"
                                >
                                    {{ u.role_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                                        u.is_active
                                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'
                                            : 'bg-slate-100 text-slate-500',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            u.is_active ? 'bg-emerald-500' : 'bg-slate-400',
                                        ]"
                                    />
                                    {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500 font-medium">{{ u.created_at }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    <Link
                                        :href="route('activities.index', { user: u.id })"
                                        class="btn-table-action"
                                    >
                                        Aktivitas
                                    </Link>
                                    <template v-if="u.role === 'karyawan'">
                                        <Link
                                            :href="route('users.edit', u.id)"
                                            class="rounded-lg border border-blue-200 bg-blue-50/50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100/60 transition-colors"
                                        >
                                            Ubah
                                        </Link>
                                        <button
                                            v-if="u.is_active"
                                            type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100/60 transition-colors"
                                            @click="deactivating = u"
                                        >
                                            Nonaktifkan
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100/60 transition-colors"
                                            @click="toggle(u)"
                                        >
                                            Aktifkan
                                        </button>
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
            title="Nonaktifkan karyawan?"
            :message="
                deactivating
                    ? `Karyawan “${deactivating.name}” tidak akan bisa login lagi ke sistem hingga diaktifkan kembali.`
                    : ''
            "
            :processing="processing"
            @close="deactivating = null"
            @confirm="toggle(deactivating, () => (deactivating = null))"
        />
    </AuthenticatedLayout>
</template>
