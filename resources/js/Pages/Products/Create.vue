<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductForm from '@/Components/ProductForm.vue';

defineProps({ categories: Array, suppliers: Array });

const form = useForm({
    code: '',
    name: '',
    category_id: '',
    supplier_id: '',
    purchase_price: 0,
    selling_price: 0,
    unit: 'pcs',
    pack_unit: '',
    units_per_pack: 1,
    min_stock: 0,
    initial_stock: 0,
    photo: null,
});

const submit = () => form.post(route('products.store'), { forceFormData: true });
</script>

<template>
    <Head title="Tambah Barang" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Tambah Barang</h2>
        </template>

        <div class="mx-auto max-w-4xl">
            <ProductForm :form="form" :categories="categories" :suppliers="suppliers" mode="create" submit-label="Simpan Barang" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
