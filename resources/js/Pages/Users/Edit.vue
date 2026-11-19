<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from '@/Components/UserForm.vue';

const props = defineProps({ user: Object });

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.put(route('users.update', props.user.id), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head :title="`Ubah ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-blue-900">Ubah Akun Karyawan</h2>
        </template>
        <div class="mx-auto max-w-3xl">
            <UserForm :form="form" mode="edit" submit-label="Simpan Perubahan" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
