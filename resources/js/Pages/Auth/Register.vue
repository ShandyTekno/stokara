<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    store_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const inputClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-800 focus:ring-blue-800';
</script>

<template>
    <GuestLayout>
        <Head title="Daftar" />

        <h1 class="text-2xl font-bold text-blue-900">Daftarkan toko Anda</h1>
        <p class="mt-1 text-sm text-gray-500">Akun ini akan menjadi admin / pemilik toko.</p>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div>
                <label for="store_name" class="block text-sm font-medium text-gray-700">Nama toko</label>
                <input id="store_name" v-model="form.store_name" type="text" required autofocus :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.store_name" />
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Nama lengkap</label>
                <input id="name" v-model="form.name" type="text" required autocomplete="name" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" v-model="form.email" type="email" required autocomplete="username" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Kata sandi</label>
                <input id="password" v-model="form.password" type="password" required autocomplete="new-password" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Ulangi kata sandi</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required
                    autocomplete="new-password" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full rounded-md bg-blue-900 px-4 py-2.5 font-semibold text-white hover:bg-blue-800 disabled:opacity-50">
                Daftar
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Sudah punya akun?
            <Link :href="route('login')" class="font-medium text-blue-800 hover:underline">Masuk</Link>
        </p>
    </GuestLayout>
</template>
