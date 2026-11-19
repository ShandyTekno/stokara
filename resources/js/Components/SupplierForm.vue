<script setup>
import { Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineProps({
    form: { type: Object, required: true },
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
                <label for="name" :class="label">Nama pemasok</label>
                <input id="name" v-model="form.name" type="text" :class="input" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <label for="company_name" :class="label">Nama perusahaan (opsional)</label>
                <input id="company_name" v-model="form.company_name" type="text" :class="input" />
                <InputError class="mt-1" :message="form.errors.company_name" />
            </div>
            <div>
                <label for="phone" :class="label">Nomor telepon</label>
                <input id="phone" v-model="form.phone" type="text" placeholder="0812-3456-7890" :class="input" />
                <InputError class="mt-1" :message="form.errors.phone" />
            </div>
            <div class="md:col-span-2">
                <label for="address" :class="label">Alamat</label>
                <textarea id="address" v-model="form.address" rows="3" :class="input" />
                <InputError class="mt-1" :message="form.errors.address" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('suppliers.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
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
