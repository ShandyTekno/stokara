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
    <nav v-if="links.length > 3" class="flex flex-col items-center justify-between gap-3 sm:flex-row pt-2">
        <p class="text-xs text-slate-500 font-medium">
            Menampilkan <span class="font-semibold text-slate-700">{{ from }}–{{ to }}</span> dari <span class="font-semibold text-slate-700">{{ total }}</span> data
        </p>
        <div class="flex flex-wrap items-center gap-1.5">
            <template v-for="(l, i) in links" :key="i">
                <Link
                    v-if="l.url"
                    :href="l.url"
                    preserve-scroll
                    :class="[
                        'min-w-[2.25rem] rounded-xl border px-3 py-1.5 text-center text-xs font-semibold shadow-xs transition-all',
                        l.active
                            ? 'border-blue-700 bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-blue-500/20'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 active:scale-95',
                    ]"
                >
                    {{ label(l) }}
                </Link>
                <span
                    v-else
                    class="min-w-[2.25rem] rounded-xl border border-slate-100 bg-slate-50 px-3 py-1.5 text-center text-xs text-slate-400"
                >
                    {{ label(l) }}
                </span>
            </template>
        </div>
    </nav>
</template>
