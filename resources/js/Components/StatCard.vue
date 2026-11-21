<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'blue' },
});

const theme = computed(() => {
    const tones = {
        blue: {
            bgGlow: 'bg-blue-500/5 group-hover:bg-blue-500/10',
            iconBox: 'bg-blue-50 text-blue-600 ring-1 ring-blue-500/20',
            accentBar: 'bg-gradient-to-r from-blue-600 to-indigo-600',
            valueColor: 'text-slate-900',
            iconPath: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5',
        },
        green: {
            bgGlow: 'bg-emerald-500/5 group-hover:bg-emerald-500/10',
            iconBox: 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/20',
            accentBar: 'bg-gradient-to-r from-emerald-500 to-teal-500',
            valueColor: 'text-slate-900',
            iconPath: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        yellow: {
            bgGlow: 'bg-amber-500/5 group-hover:bg-amber-500/10',
            iconBox: 'bg-amber-50 text-amber-600 ring-1 ring-amber-500/20',
            accentBar: 'bg-gradient-to-r from-amber-500 to-yellow-500',
            valueColor: 'text-slate-900',
            iconPath: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        },
        red: {
            bgGlow: 'bg-rose-500/5 group-hover:bg-rose-500/10',
            iconBox: 'bg-rose-50 text-rose-600 ring-1 ring-rose-500/20',
            accentBar: 'bg-gradient-to-r from-rose-500 to-red-600',
            valueColor: 'text-rose-600',
            iconPath: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
        },
    };
    return tones[props.tone] ?? tones.blue;
});
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_2px_10px_-3px_rgba(15,23,42,0.05)] transition-all duration-300 ease-out hover:-translate-y-1 hover:border-slate-200 hover:shadow-[0_12px_28px_-6px_rgba(15,23,42,0.09)]"
    >
        <!-- Subtle top accent line -->
        <div :class="['absolute inset-x-0 top-0 h-1 transition-opacity duration-300', theme.accentBar]" />

        <!-- Soft ambient background light -->
        <div
            :class="[
                'pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full blur-2xl transition-all duration-300',
                theme.bgGlow,
            ]"
        />

        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ title }}</p>
                <p :class="['mt-2 text-2xl font-bold tracking-tight sm:text-3xl', theme.valueColor]">
                    {{ value }}
                </p>
            </div>
            <div
                :class="[
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-xs transition-transform duration-300 ease-out group-hover:scale-110',
                    theme.iconBox,
                ]"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="theme.iconPath" />
                </svg>
            </div>
        </div>

        <p v-if="hint" class="relative mt-3 text-xs text-slate-400">
            {{ hint }}
        </p>
    </div>
</template>
