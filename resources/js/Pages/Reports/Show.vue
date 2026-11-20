<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StockBadge from '@/Components/StockBadge.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({
    report: Object,
    rowsTotal: Number,
    screenRows: Number,
    filters: Object,
    categories: Array,
    meta: Object,
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const category = ref(props.filters.category ?? '');
const status = ref(props.filters.status ?? '');
let timer = null;

watch([from, to, category, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('reports.show', { type: props.meta.slug }),
            {
                from: props.meta.period ? from.value || undefined : undefined,
                to: props.meta.period ? to.value || undefined : undefined,
                category: category.value || undefined,
                status: props.meta.status ? status.value || undefined : undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 400);
});
onBeforeUnmount(() => clearTimeout(timer));

// Ekspor memakai filter yang sedang diterapkan di server.
const exportParams = computed(() => ({
    type: props.meta.slug,
    ...(props.meta.period ? { from: props.filters.from, to: props.filters.to } : {}),
    ...(props.filters.category ? { category: props.filters.category } : {}),
    ...(props.meta.status && props.filters.status ? { status: props.filters.status } : {}),
}));

function display(value, type) {
    if (type === 'money') return formatRupiah(value);
    if (type === 'number') return formatNumber(value);
    return value === null || value === '' || value === undefined ? '—' : value;
}

const isRight = (col) => col.type === 'money' || col.type === 'number';

const field =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head :title="report.title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">{{ report.title }}</h2>
        </template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <template v-if="meta.period">
                        <div>
                            <label class="block text-xs text-gray-500">Dari tanggal</label>
                            <input v-model="from" type="date" :class="field" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Sampai tanggal</label>
                            <input v-model="to" type="date" :class="field" />
                        </div>
                    </template>
                    <div>
                        <label class="block text-xs text-gray-500">Kategori</label>
                        <select v-model="category" :class="[field, 'w-full sm:w-48']">
                            <option value="">Semua kategori</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="meta.status">
                        <label class="block text-xs text-gray-500">Status stok</label>
                        <select v-model="status" :class="[field, 'w-full sm:w-44']">
                            <option value="">Semua status</option>
                            <option value="aman">Stok aman</option>
                            <option value="menipis">Stok menipis</option>
                            <option value="habis">Stok habis</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <a :href="route('reports.pdf', exportParams)" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Unduh PDF
                    </a>
                    <a :href="route('reports.excel', exportParams)" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                        Unduh Excel
                    </a>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-500">
                <p>{{ report.filters_text.join(' · ') }}</p>
                <Link :href="route('reports.index')" class="text-blue-800 hover:underline">← Semua laporan</Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard
                    v-for="item in report.summary"
                    :key="item.label"
                    :title="item.label"
                    :value="display(item.value, item.type)"
                    :tone="item.tone ?? 'blue'"
                />
            </div>

            <section v-if="report.chart" class="rounded-xl bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-semibold text-blue-900">{{ report.chart.title }}</h3>
                <LineChart
                    v-if="report.chart.kind === 'line'"
                    :labels="report.chart.labels"
                    :datasets="report.chart.datasets"
                    :currency="report.chart.currency"
                />
                <BarChart v-else :labels="report.chart.labels" :datasets="report.chart.datasets" />
            </section>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th v-for="col in report.columns" :key="col.key" :class="['px-4 py-3', isRight(col) ? 'text-right' : '']">
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!report.rows.length">
                            <td :colspan="report.columns.length" class="px-4 py-10 text-center text-gray-500">
                                Tidak ada data pada filter ini.
                            </td>
                        </tr>
                        <tr v-for="(row, i) in report.rows" :key="i" class="hover:bg-gray-50">
                            <td v-for="col in report.columns" :key="col.key" :class="['px-4 py-2.5', isRight(col) ? 'text-right' : '']">
                                <StockBadge v-if="col.type === 'status'" :status="row[col.key]" />
                                <template v-else>{{ display(row[col.key], col.type) }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="rowsTotal > screenRows" class="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-900">
                Menampilkan {{ screenRows }} dari {{ formatNumber(rowsTotal) }} baris. Unduh PDF atau Excel untuk data lengkap.
            </p>

            <ul v-if="report.notes.length" class="list-disc space-y-1 pl-5 text-xs text-gray-500">
                <li v-for="note in report.notes" :key="note">{{ note }}</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
