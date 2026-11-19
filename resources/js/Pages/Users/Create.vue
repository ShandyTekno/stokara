<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from '@/Components/UserForm.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const submit = () =>
    form.post(route('users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Tambah Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Tambah Karyawan</h2>
        </template>
        <div class="mx-auto max-w-3xl">
            <UserForm :form="form" mode="create" submit-label="Buat Akun" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
