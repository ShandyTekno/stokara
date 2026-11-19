<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
});

const label = (l) => (/prev/i.test(l.label) ? '‹' : /next/i.test(l.label) ? '›' : l.label);
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-gray-600">Menampilkan {{ from }}–{{ to }} dari {{ total }} data</p>
        <div class="flex flex-wrap gap-1">
            <template v-for="(l, i) in links" :key="i">
                <Link
                    v-if="l.url"
                    :href="l.url"
                    preserve-scroll
                    :class="[
                        'min-w-[2.25rem] rounded-md border px-3 py-1.5 text-center text-sm',
                        l.active
                            ? 'border-blue-900 bg-blue-900 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                    ]"
                >
                    {{ label(l) }}
                </Link>
                <span
                    v-else
                    class="min-w-[2.25rem] rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-center text-sm text-gray-400"
                >
                    {{ label(l) }}
                </span>
            </template>
        </div>
    </nav>
</template>
