<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductForm from '@/Components/ProductForm.vue';

const props = defineProps({ product: Object, categories: Array, suppliers: Array });

const form = useForm({
    _method: 'put', // upload file butuh POST multipart, Laravel membacanya sebagai PUT
    code: props.product.code,
    name: props.product.name,
    category_id: props.product.category_id ?? '',
    supplier_id: props.product.supplier_id ?? '',
    purchase_price: props.product.purchase_price,
    selling_price: props.product.selling_price,
    unit: props.product.unit,
    pack_unit: props.product.pack_unit ?? '',
    units_per_pack: props.product.units_per_pack,
    min_stock: props.product.min_stock,
    photo: null,
    remove_photo: false,
});

const submit = () => form.post(route('products.update', props.product.id), { forceFormData: true });
</script>

<template>
    <Head :title="`Ubah ${product.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Ubah Barang</h2>
        </template>

        <div class="mx-auto max-w-4xl">
            <ProductForm
                :form="form"
                :categories="categories"
                :suppliers="suppliers"
                mode="edit"
                :current-stock="product.stock"
                :current-photo-url="product.photo_url"
                submit-label="Simpan Perubahan"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
