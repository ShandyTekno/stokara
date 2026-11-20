<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({ reports: Array });
</script>

<template>
    <Head title="Laporan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Laporan</h2>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="r in reports"
                :key="r.slug"
                :href="route('reports.show', { type: r.slug })"
                class="rounded-xl border-l-4 border-blue-900 bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <h3 class="font-semibold text-blue-900">{{ r.label }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ r.description }}</p>
                <p class="mt-3 text-sm font-medium text-blue-800">Buka laporan →</p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
