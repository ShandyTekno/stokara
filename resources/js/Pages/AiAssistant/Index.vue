<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChatMessageContent from '@/Components/ChatMessageContent.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    mode: String,
    model: String,
    conversations: Array,
    conversation: Object,
    messages: Array,
    suggestions: Array,
    limits: Object,
});

const form = useForm({ message: '', conversation_id: null });
const pending = ref(null);
const scroller = ref(null);
const showHistory = ref(false);

function scrollDown() {
    nextTick(() => {
        if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
    });
}

watch(() => [props.messages.length, pending.value], scrollDown);
onMounted(scrollDown);

function send(text) {
    const message = (text ?? form.message).trim();
    if (!message || form.processing) return;

    form.message = message;
    form.conversation_id = props.conversation?.id ?? null;
    pending.value = message;

    form.post(route('ai.assistant.send'), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => form.reset('message'),
        onFinish: () => (pending.value = null),
    });
}

const deleting = ref(null);
const deleteProcessing = ref(false);

function confirmDelete() {
    router.delete(route('ai.assistant.destroy', deleting.value.id), {
        onStart: () => (deleteProcessing.value = true),
        onFinish: () => {
            deleteProcessing.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="AI Assistant" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-blue-900">AI Stock Assistant</h2>
                <span
                    v-if="mode === 'demo'"
                    class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800"
                >
                    MODE DEMO · SIMULASI
                </span>
                <span v-else class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">
                    AI aktif · {{ model }}
                </span>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="mode === 'demo'"
                class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900"
            >
                <strong>Mode demo.</strong> API AI belum dikonfigurasi, jadi jawaban di bawah adalah
                <strong>simulasi</strong> yang dibuat dari data nyata toko Anda dengan aturan sederhana, bukan AI
                sungguhan. Isi <code>GEMINI_API_KEY</code> di file <code>.env</code> lalu jalankan
                <code>php artisan config:clear</code> untuk mengaktifkan AI.
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <aside class="lg:col-span-1">
                    <div class="flex items-center justify-between gap-2 lg:block">
                        <Link
                            :href="route('ai.assistant')"
                            class="block rounded-md bg-blue-900 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-800"
                        >
                            + Percakapan baru
                        </Link>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 lg:hidden"
                            @click="showHistory = !showHistory"
                        >
                            Riwayat
                        </button>
                    </div>

                    <div :class="[showHistory ? 'block' : 'hidden', 'mt-3 lg:block']">
                        <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Riwayat percakapan</p>
                        <p v-if="!conversations.length" class="rounded-md bg-white p-3 text-sm text-gray-500 shadow-sm">
                            Belum ada percakapan.
                        </p>
                        <ul v-else class="max-h-96 space-y-1 overflow-y-auto">
                            <li
                                v-for="c in conversations"
                                :key="c.id"
                                :class="[
                                    'group flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                                    conversation?.id === c.id ? 'bg-blue-100' : 'bg-white shadow-sm hover:bg-gray-50',
                                ]"
                            >
                                <Link :href="route('ai.assistant.show', c.id)" class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-gray-900">{{ c.title }}</p>
                                    <p class="text-xs text-gray-500">{{ c.time }}</p>
                                </Link>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-red-600"
                                    title="Hapus percakapan"
                                    @click="deleting = c"
                                >
                                    ✕
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>

                <section class="flex h-[calc(100dvh-15rem)] min-h-[28rem] flex-col rounded-xl bg-white shadow-sm lg:col-span-3">
                    <div ref="scroller" class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                        <div v-if="!messages.length && !pending" class="mx-auto max-w-xl py-6 text-center">
                            <h3 class="text-lg font-semibold text-blue-900">Tanya apa saja tentang stok toko Anda</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Jawaban memakai data aktual toko Anda. Bila data belum cukup, asisten akan mengatakannya.
                            </p>
                            <div class="mt-6 grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="s in suggestions"
                                    :key="s"
                                    type="button"
                                    :disabled="form.processing"
                                    class="rounded-lg border border-gray-200 px-3 py-2 text-left text-sm text-gray-700 hover:border-blue-800 hover:bg-blue-50 disabled:opacity-50"
                                    @click="send(s)"
                                >
                                    {{ s }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="m in messages"
                            :key="m.id"
                            :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']"
                        >
                            <div
                                :class="[
                                    'max-w-[85%] rounded-2xl px-4 py-3 text-sm',
                                    m.role === 'user' ? 'bg-blue-900 text-white' : 'border bg-gray-50 text-gray-900',
                                ]"
                            >
                                <span
                                    v-if="m.is_simulation"
                                    class="mb-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-yellow-800"
                                >
                                    Simulasi
                                </span>
                                <ChatMessageContent :text="m.content" />
                                <p class="mt-1 text-[10px] opacity-60">{{ m.time }}</p>
                            </div>
                        </div>

                        <div v-if="pending" class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl bg-blue-900 px-4 py-3 text-sm text-white">
                                <ChatMessageContent :text="pending" />
                            </div>
                        </div>
                        <div v-if="form.processing" class="flex justify-start">
                            <div class="flex items-center gap-3 rounded-2xl border border-blue-100 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm">
                                <svg
                                    class="h-5 w-5 animate-spin text-blue-900"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                    ></path>
                                </svg>
                                <div class="flex flex-col">
                                    <span class="font-medium text-blue-900">AI sedang berpikir…</span>
                                    <span class="text-xs text-gray-500">Menganalisis data stok &amp; menyusun jawaban</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form class="border-t p-3 sm:p-4" @submit.prevent="send()">
                        <InputError class="mb-2" :message="form.errors.message || form.errors.conversation_id" />
                        <div class="flex items-end gap-2">
                            <textarea
                                v-model="form.message"
                                rows="2"
                                :maxlength="limits.max_length"
                                :disabled="form.processing"
                                placeholder="Tulis pertanyaan tentang stok atau penjualan… (Enter untuk kirim)"
                                class="block w-full resize-none rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800 disabled:bg-gray-50"
                                @keydown.enter.exact.prevent="send()"
                            />
                            <button
                                type="submit"
                                :disabled="form.processing || !form.message.trim()"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-4 w-4 animate-spin text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                    ></path>
                                </svg>
                                <span>{{ form.processing ? 'Memproses…' : 'Kirim' }}</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            <template v-if="mode === 'live'">
                                Untuk menjawab, ringkasan data toko (nama barang, stok, dan angka penjualan) dikirim ke
                                Google Gemini. Email dan kata sandi tidak dikirim. AI bisa keliru, jadi cek angka penting di
                                menu Laporan.
                            </template>
                            <template v-else>
                                Mode demo tidak mengirim data ke pihak luar; jawaban dibuat di server Anda.
                            </template>
                        </p>
                    </form>
                </section>
            </div>
        </div>

        <ConfirmModal
            :show="!!deleting"
            title="Hapus percakapan?"
            :message="deleting ? `Percakapan “${deleting.title}” beserta seluruh pesannya akan dihapus.` : ''"
            :processing="deleteProcessing"
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
