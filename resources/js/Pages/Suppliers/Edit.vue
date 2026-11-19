<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SupplierForm from '@/Components/SupplierForm.vue';

const props = defineProps({ supplier: Object });

const form = useForm({
    name: props.supplier.name,
    company_name: props.supplier.company_name ?? '',
    phone: props.supplier.phone ?? '',
    address: props.supplier.address ?? '',
});
const submit = () => form.put(route('suppliers.update', props.supplier.id));
</script>

<template>
    <Head :title="`Ubah ${supplier.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Ubah Pemasok</h2>
        </template>
        <div class="mx-auto max-w-3xl">
            <SupplierForm :form="form" submit-label="Simpan Perubahan" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
