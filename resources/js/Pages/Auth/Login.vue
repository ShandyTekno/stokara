<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <h1 class="text-2xl font-bold text-blue-900">Masuk ke StokCerdas</h1>
        <p class="mt-1 text-sm text-gray-500">Kelola persediaan toko Anda.</p>

        <div v-if="status" class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-800 focus:ring-blue-800" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Kata sandi</label>
                <input id="password" v-model="form.password" type="password" required autocomplete="current-password"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-800 focus:ring-blue-800" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remember" type="checkbox"
                        class="rounded border-gray-300 text-blue-900 focus:ring-blue-800" />
                    Ingat saya
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')"
                    class="text-sm text-blue-800 hover:underline">
                    Lupa kata sandi?
                </Link>
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full rounded-md bg-blue-900 px-4 py-2.5 font-semibold text-white hover:bg-blue-800 disabled:opacity-50">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Belum punya toko?
            <Link :href="route('register')" class="font-medium text-blue-800 hover:underline">Daftar sebagai pemilik toko</Link>
        </p>
    </GuestLayout>
</template>
