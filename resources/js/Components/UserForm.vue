<script setup>
import { Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineProps({
    form: { type: Object, required: true },
    mode: { type: String, default: 'create' },
    submitLabel: { type: String, default: 'Simpan' },
});
defineEmits(['submit']);

const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
const label = 'block text-sm font-medium text-gray-700';
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2">
            <div>
                <label for="name" :class="label">Nama lengkap</label>
                <input id="name" v-model="form.name" type="text" :class="input" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <label for="email" :class="label">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="off" :class="input" required />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-gray-500">
                    <template v-if="mode === 'create'">Akun ini akan dibuat dengan role Karyawan.</template>
                    <template v-else>Kosongkan kata sandi jika tidak ingin mengubahnya. Mengganti kata sandi akan mengeluarkan karyawan dari semua perangkat.</template>
                </p>
            </div>
            <div>
                <label for="password" :class="label">{{ mode === 'create' ? 'Kata sandi' : 'Kata sandi baru' }}</label>
                <input id="password" v-model="form.password" type="password" autocomplete="new-password" :class="input" :required="mode === 'create'" />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" :class="label">Ulangi kata sandi</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="input" :required="mode === 'create'" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('users.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-blue-900 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
