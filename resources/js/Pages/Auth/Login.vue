<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const page = usePage();
const sessionError = computed(() => page.props.errors?.email);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Masuk ke Akun</h1>
            <p class="mt-1 text-sm text-slate-500">Masukkan kredensial Anda untuk mengelola toko.</p>
        </div>

        <div v-if="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-800">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-xs transition-colors focus:border-blue-600 focus:ring-blue-600"
                    placeholder="nama@email.com"
                />
                <InputError class="mt-1.5" :message="form.errors.email || sessionError" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-xs transition-colors focus:border-blue-600 focus:ring-blue-600"
                    placeholder="••••••••"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded-md border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Ingat saya</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-xs font-semibold text-blue-700 hover:text-blue-900 hover:underline"
                >
                    Lupa kata sandi?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 rounded-xl bg-gradient-to-r from-blue-700 to-indigo-700 py-3 px-4 text-sm font-bold text-white shadow-md shadow-blue-600/20 hover:from-blue-800 hover:to-indigo-800 active:scale-[0.99] transition-all disabled:opacity-50"
            >
                {{ form.processing ? 'Memproses...' : 'Masuk Sekarang' }}
            </button>
        </form>

        <div class="mt-6 border-t border-slate-100 pt-5 text-center text-xs text-slate-500">
            Belum punya akun toko?
            <Link :href="route('register')" class="ml-1 font-semibold text-blue-700 hover:text-blue-900 hover:underline">
                Daftar sebagai pemilik toko
            </Link>
        </div>
    </GuestLayout>
</template>
