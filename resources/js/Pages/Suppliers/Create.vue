<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SupplierForm from '@/Components/SupplierForm.vue';

const form = useForm({ name: '', company_name: '', phone: '', address: '' });
const submit = () => form.post(route('suppliers.store'));
</script>

<template>
    <Head title="Tambah Pemasok" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Tambah Pemasok</h2>
        </template>
        <div class="mx-auto max-w-3xl">
            <SupplierForm :form="form" submit-label="Simpan Pemasok" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
