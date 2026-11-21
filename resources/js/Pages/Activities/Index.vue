<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatNumber } from '@/utils/format';

const props = defineProps({ activities: Object, summary: Array, users: Array, filters: Object });

const user = ref(props.filters.user ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
let timer = null;

watch([user, from, to], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('activities.index'),
            { user: user.value || undefined, from: from.value || undefined, to: to.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

function reset() {
    user.value = '';
    from.value = '';
    to.value = '';
}

const field =
    'rounded-xl border-slate-300 text-sm shadow-xs focus:border-blue-600 focus:ring-blue-600';
</script>

<template>
    <Head title="Aktivitas Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Log Aktivitas</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10">
                    Audit Trail
                </span>
            </div>
        </template>

        <div class="space-y-5">
            <!-- Filter Toolbar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Pengguna</label>
                    <select v-model="user" :class="[field, 'w-full sm:w-52']">
                        <option value="">Semua Pengguna</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                    <input v-model="from" type="date" :class="field" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                    <input v-model="to" type="date" :class="field" />
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 active:scale-95 transition-all"
                    @click="reset"
                >
                    Reset Filter
                </button>
            </div>

            <!-- Summary Chips -->
            <div v-if="summary.length" class="flex flex-wrap gap-2.5">
                <div
                    v-for="s in summary"
                    :key="s.user"
                    class="rounded-xl border border-slate-200/80 bg-white px-3.5 py-2 shadow-sm card-hover flex items-center gap-3"
                >
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-700 font-bold text-xs">
                        {{ s.user.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-800">{{ s.user }}</p>
                        <p class="text-[11px] font-medium text-blue-600">{{ formatNumber(s.total) }} aktivitas</p>
                    </div>
                </div>
            </div>

            <!-- Modern Table Container -->
            <div class="modern-table-box">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Pengguna</th>
                            <th class="px-5 py-3.5">Tipe Aktivitas</th>
                            <th class="px-5 py-3.5">Barang Terkait</th>
                            <th class="px-5 py-3.5 text-right">Perubahan Stok</th>
                            <th class="px-5 py-3.5">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!activities.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                Tidak ada catatan aktivitas pada filter yang dipilih.
                            </td>
                        </tr>
                        <tr
                            v-for="a in activities.data"
                            :key="a.id"
                            class="group table-row-modern"
                        >
                            <td class="px-5 py-3.5 text-xs text-slate-500 font-medium whitespace-nowrap">{{ a.time }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-700">
                                        {{ a.user.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="font-semibold text-slate-800 text-xs">{{ a.user }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="rounded-lg bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    {{ a.type }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-medium text-slate-800">{{ a.product ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold',
                                        a.change >= 0
                                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'
                                            : 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
                                    ]"
                                >
                                    {{ a.change >= 0 ? '+' : '' }}{{ formatNumber(a.change) }} {{ a.unit }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ a.notes ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="activities.links" :from="activities.from ?? 0" :to="activities.to ?? 0" :total="activities.total" />
        </div>
    </AuthenticatedLayout>
</template>
