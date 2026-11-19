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
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Aktivitas Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Aktivitas Pengguna</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label class="block text-xs text-gray-500">Pengguna</label>
                    <select v-model="user" :class="[field, 'w-full sm:w-52']">
                        <option value="">Semua pengguna</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
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

            <div v-if="summary.length" class="flex flex-wrap gap-3">
                <div v-for="s in summary" :key="s.user" class="rounded-lg border-l-4 border-blue-900 bg-white px-4 py-2 shadow-sm">
                    <p class="text-xs text-gray-500">{{ s.user }}</p>
                    <p class="text-lg font-bold text-blue-900">{{ formatNumber(s.total) }} aktivitas</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Pengguna</th>
                            <th class="px-4 py-3">Aktivitas</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 text-right">Perubahan stok</th>
                            <th class="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!activities.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada aktivitas pada filter ini.</td>
                        </tr>
                        <tr v-for="a in activities.data" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ a.time }}</td>
                            <td class="px-4 py-3">{{ a.user }}</td>
                            <td class="px-4 py-3">{{ a.type }}</td>
                            <td class="px-4 py-3">{{ a.product ?? '—' }}</td>
                            <td :class="['px-4 py-3 text-right font-semibold', a.change >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ a.change >= 0 ? '+' : '' }}{{ formatNumber(a.change) }} {{ a.unit }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ a.notes ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="activities.links" :from="activities.from ?? 0" :to="activities.to ?? 0" :total="activities.total" />
        </div>
    </AuthenticatedLayout>
</template>
