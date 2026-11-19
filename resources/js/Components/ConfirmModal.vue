<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Hapus' },
    processing: { type: Boolean, default: false },
});

defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
            <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    @click="$emit('close')"
                >
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    @click="$emit('confirm')"
                >
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </Modal>
</template>
