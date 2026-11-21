<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import InputError from '@/Components/InputError.vue';
import ChatMessageContent from '@/Components/ChatMessageContent.vue';
import { formatNumber, formatRupiah } from '@/utils/format';

const props = defineProps({
    mode: String,
    model: String,
    filters: Object,
    options: Object,
    categories: Array,
    info: Object,
    notes: Array,
    summary: Object,
    formulas: Array,
    definitions: Array,
    reorder: Object,
    risk: Object,
    slow: Object,
    screenRows: Number,
    explanation: Object,
});

const period = ref(props.filters.period);
const cover = ref(props.filters.cover);
const category = ref(props.filters.category ?? '');
let timer = null;

watch([period, cover, category], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('ai.analysis'),
            { period: period.value, cover: cover.value, category: category.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

// Ekspor memakai filter yang sedang diterapkan di server.
const exportParams = computed(() => ({
    period: props.filters.period,
    cover: props.filters.cover,
    ...(props.filters.category ? { category: props.filters.category } : {}),
}));

const explainForm = useForm({ period: props.filters.period, cover: props.filters.cover, category: '' });

function explain() {
    explainForm.period = props.filters.period;
    explainForm.cover = props.filters.cover;
    explainForm.category = props.filters.category ?? '';
    explainForm.post(route('ai.analysis.explain'), {
        preserveScroll: true,
        showProgress: false,
    });
}

const urgency = {
    habis: { label: 'Habis', css: 'bg-red-100 text-red-800' },
    mendesak: { label: 'Mendesak', css: 'bg-yellow-100 text-yellow-800' },
    direncanakan: { label: 'Direncanakan', css: 'bg-blue-100 text-blue-900' },
};

const dec = (v) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(v) || 0);
const days = (v) => (v === null || v === undefined ? '—' : v > 365 ? '> 365 hari' : `${dec(v)} hari`);
const money = (v) => (v === null || v === undefined ? '—' : formatRupiah(v));

const field =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <Head title="Analisis AI" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-blue-900">Analisis AI · Rekomendasi Persediaan</h2>
                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-900">
                    Perhitungan rata-rata sederhana
                </span>
            </div>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label class="block text-xs text-gray-500">Periode analisis</label>
                    <select v-model="period" :class="[field, 'w-full sm:w-40']">
                        <option v-for="p in options.periods" :key="p" :value="p">{{ p }} hari terakhir</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">Cakupan stok</label>
                    <select v-model="cover" :class="[field, 'w-full sm:w-44']">
                        <option v-for="c in options.covers" :key="c" :value="c">{{ c }} hari ke depan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">Kategori</label>
                    <select v-model="category" :class="[field, 'w-full sm:w-48']">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
            </div>

            <p class="text-sm text-gray-500">
                <template v-if="info.has_sales">
                    Dihitung dari penjualan {{ info.data_days }} hari (periode analisis {{ info.period }} hari) · cakupan stok
                    {{ info.cover }} hari ke depan<template v-if="info.category_name"> · kategori {{ info.category_name }}</template>.
                </template>
                <template v-else>Belum ada data penjualan, sehingga kebutuhan barang belum bisa dihitung.</template>
            </p>

            <div v-if="notes.length" class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
                <p v-for="n in notes" :key="n">{{ n }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Barang perlu dibeli"
                    :value="formatNumber(summary.reorder_count)"
                    :hint="`${formatNumber(summary.urgent_count)} habis atau mendesak`"
                    tone="yellow"
                />
                <StatCard
                    title="Perkiraan biaya pengadaan"
                    :value="info.has_sales ? formatRupiah(summary.reorder_cost) : '—'"
                    hint="Saran beli × harga beli"
                    tone="blue"
                />
                <StatCard
                    title="Berpotensi kehabisan"
                    :value="formatNumber(summary.risk_count)"
                    :hint="`Habis dalam kurang dari ${info.cover} hari`"
                    tone="red"
                />
                <StatCard
                    title="Perputaran lambat"
                    :value="formatNumber(summary.slow_count)"
                    :hint="`Modal tertahan ${formatRupiah(summary.slow_value)}`"
                    tone="blue"
                />
            </div>

            <!-- 1. Prioritas pengadaan -->
            <section class="rounded-xl bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-blue-900">1. Prioritas pengadaan</h3>
                        <p class="text-sm text-gray-500">Barang yang sebaiknya dibeli lebih dulu, dari yang paling mendesak.</p>
                    </div>
                    <div v-if="reorder.total" class="flex gap-2">
                        <a :href="route('ai.analysis.pdf', exportParams)" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                            Unduh PDF
                        </a>
                        <a :href="route('ai.analysis.excel', exportParams)" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                            Unduh Excel
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3">Tingkat</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3 text-right">Rata-rata/hari</th>
                                <th class="px-4 py-3 text-right">Sisa hari</th>
                                <th class="px-4 py-3 text-right">Saran beli</th>
                                <th class="px-4 py-3 text-right">Perkiraan biaya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!reorder.rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada barang yang perlu dibeli.</td>
                            </tr>
                            <tr v-for="r in reorder.rows" :key="r.id" class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('products.show', r.id)" class="font-medium text-gray-900 hover:text-blue-800">{{ r.name }}</Link>
                                    <span v-if="r.is_new" class="ml-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-900">Baru</span>
                                    <p class="text-xs text-gray-500">{{ r.code }}<template v-if="r.supplier"> · {{ r.supplier }}</template></p>
                                    <p class="mt-1 max-w-md text-xs text-gray-500">{{ r.reason }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', urgency[r.urgency].css]">
                                        {{ urgency[r.urgency].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(r.stock) }} {{ r.unit }}</td>
                                <td class="px-4 py-3 text-right">{{ r.avg_daily === null ? '—' : dec(r.avg_daily) }}</td>
                                <td class="px-4 py-3 text-right">{{ r.days_left === null ? '—' : days(r.days_left) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <template v-if="r.suggested !== null">
                                        <span class="font-semibold text-blue-900">{{ formatNumber(r.suggested) }} {{ r.unit }}</span>
                                        <span v-if="r.suggested_packs" class="block text-xs text-gray-500">≈ {{ r.suggested_packs }} {{ r.pack_unit }}</span>
                                    </template>
                                    <span v-else class="text-xs text-gray-400">Data belum cukup</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ money(r.estimated_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="reorder.total > reorder.rows.length" class="border-t bg-blue-50 px-5 py-2 text-sm text-blue-900">
                    Menampilkan {{ reorder.rows.length }} dari {{ formatNumber(reorder.total) }} barang. Unduh PDF atau Excel untuk daftar lengkap.
                </p>
            </section>

            <!-- 2. Berpotensi kehabisan -->
            <section class="rounded-xl bg-white shadow-sm">
                <div class="border-b p-5">
                    <h3 class="font-semibold text-blue-900">2. Berpotensi kehabisan stok</h3>
                    <p class="text-sm text-gray-500">Barang berstok yang diperkirakan habis sebelum {{ info.cover }} hari berakhir.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3 text-right">Rata-rata/hari</th>
                                <th class="px-4 py-3 text-right">Sisa hari</th>
                                <th class="px-4 py-3 text-right">Perkiraan habis</th>
                                <th class="px-4 py-3 text-right">Saran beli</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!risk.rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                    {{ info.has_sales ? 'Tidak ada barang yang diperkirakan habis dalam cakupan ini.' : 'Data penjualan belum mencukupi untuk bagian ini.' }}
                                </td>
                            </tr>
                            <tr v-for="r in risk.rows" :key="r.id" class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('products.show', r.id)" class="font-medium text-gray-900 hover:text-blue-800">{{ r.name }}</Link>
                                    <span v-if="r.is_new" class="ml-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-900">Baru</span>
                                    <p class="text-xs text-gray-500">{{ r.code }}</p>
                                    <p class="mt-1 max-w-md text-xs text-gray-500">{{ r.reason }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(r.stock) }} {{ r.unit }}</td>
                                <td class="px-4 py-3 text-right">{{ dec(r.avg_daily) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-red-600">{{ days(r.days_left) }}</td>
                                <td class="px-4 py-3 text-right">{{ r.out_date }}</td>
                                <td class="px-4 py-3 text-right">{{ r.suggested > 0 ? `${formatNumber(r.suggested)} ${r.unit}` : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="risk.total > risk.rows.length" class="border-t bg-blue-50 px-5 py-2 text-sm text-blue-900">
                    Menampilkan {{ risk.rows.length }} dari {{ formatNumber(risk.total) }} barang.
                </p>
            </section>

            <!-- 3. Perputaran lambat -->
            <section class="rounded-xl bg-white shadow-sm">
                <div class="border-b p-5">
                    <h3 class="font-semibold text-blue-900">3. Perputaran lambat dan stok berlebih</h3>
                    <p class="text-sm text-gray-500">Barang berstok yang tidak terjual atau stoknya cukup lebih dari 60 hari. Tunda pembelian barang ini.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3 text-right">Terjual (periode)</th>
                                <th class="px-4 py-3 text-right">Sisa hari</th>
                                <th class="px-4 py-3">Terakhir terjual</th>
                                <th class="px-4 py-3 text-right">Modal tertahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!slow.rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                    {{ info.has_sales ? 'Tidak ada barang dengan perputaran lambat.' : 'Data penjualan belum mencukupi untuk bagian ini.' }}
                                </td>
                            </tr>
                            <tr v-for="r in slow.rows" :key="r.id" class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('products.show', r.id)" class="font-medium text-gray-900 hover:text-blue-800">{{ r.name }}</Link>
                                    <span v-if="r.is_new" class="ml-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-900">Baru</span>
                                    <p class="text-xs text-gray-500">{{ r.code }}</p>
                                    <p class="mt-1 max-w-md text-xs text-gray-500">{{ r.reason }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(r.stock) }} {{ r.unit }}</td>
                                <td class="px-4 py-3 text-right">{{ formatNumber(r.sold) }}</td>
                                <td class="px-4 py-3 text-right">{{ r.days_left === null ? 'Tidak terjual' : days(r.days_left) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ r.last_sold }}</td>
                                <td class="px-4 py-3 text-right font-medium text-yellow-700">{{ formatRupiah(r.tied_value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="slow.total > slow.rows.length" class="border-t bg-blue-50 px-5 py-2 text-sm text-blue-900">
                    Menampilkan {{ slow.rows.length }} dari {{ formatNumber(slow.total) }} barang.
                </p>
            </section>

            <!-- Penjelasan AI -->
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-blue-900">Penjelasan dengan bahasa sederhana</h3>
                        <p class="text-sm text-gray-500">
                            AI hanya menjelaskan hasil perhitungan di atas. Angkanya tidak dihitung oleh AI.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="explainForm.processing"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                        @click="explain"
                    >
                        <svg
                            v-if="explainForm.processing"
                            class="h-4 w-4 animate-spin text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>{{ explainForm.processing ? 'Menyusun penjelasan…' : explanation ? 'Buat ulang penjelasan' : 'Jelaskan hasil analisis' }}</span>
                    </button>
                </div>

                <InputError class="mt-3" :message="explainForm.errors.explain" />

                <div v-if="explanation" class="mt-4 rounded-lg border bg-gray-50 p-4 text-sm text-gray-900">
                    <span
                        v-if="explanation.is_simulation"
                        class="mb-2 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-yellow-800"
                    >
                        Simulasi
                    </span>
                    <ChatMessageContent :text="explanation.text" />
                    <p class="mt-2 text-[10px] text-gray-400">
                        {{ explanation.generated_at }}<template v-if="explanation.model"> · {{ explanation.model }}</template>
                    </p>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    <template v-if="mode === 'live'">
                        Untuk menyusun penjelasan, ringkasan hasil analisis (nama barang, stok, dan angka penjualan) dikirim
                        ke Google Gemini. Email dan kata sandi tidak dikirim. AI bisa keliru, jadi cek angka penting pada tabel
                        di atas. Penjelasan tidak disimpan dan hilang saat halaman dipindah.
                    </template>
                    <template v-else>
                        Mode demo: penjelasan berupa <strong>simulasi</strong> yang dibuat di server Anda dan tidak mengirim
                        data ke pihak luar. Isi <code>GEMINI_API_KEY</code> di <code>.env</code> untuk penjelasan AI sungguhan.
                    </template>
                </p>
            </section>

            <details class="rounded-xl bg-white p-5 shadow-sm">
                <summary class="cursor-pointer font-semibold text-blue-900">Cara perhitungan</summary>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li v-for="f in formulas" :key="f">{{ f }}</li>
                </ul>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li v-for="d in definitions" :key="d">{{ d }}</li>
                </ul>
                <p class="mt-3 text-sm text-gray-600">
                    Ini perhitungan rata-rata sederhana, <strong>bukan prediksi machine learning</strong>. Musim ramai,
                    promo, dan perubahan harga tidak diperhitungkan, jadi anggap angkanya sebagai perkiraan.
                </p>
            </details>
        </div>
    </AuthenticatedLayout>
</template>
