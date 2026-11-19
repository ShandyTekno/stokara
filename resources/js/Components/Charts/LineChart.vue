<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS, Tooltip, Legend, LineElement, PointElement,
    LinearScale, CategoryScale, Filler,
} from 'chart.js';
import { formatRupiah } from '@/utils/format';

ChartJS.register(Tooltip, Legend, LineElement, PointElement, LinearScale, CategoryScale, Filler);

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    currency: { type: Boolean, default: false },
});

const data = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((d) => ({
        fill: true,
        tension: 0.3,
        borderColor: '#1e3a8a',
        backgroundColor: 'rgba(30, 58, 138, 0.1)',
        pointBackgroundColor: '#1e3a8a',
        ...d,
    })),
}));

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: props.datasets.length > 1 },
        tooltip: {
            callbacks: {
                label: (ctx) => (props.currency ? ` ${formatRupiah(ctx.parsed.y)}` : ` ${ctx.parsed.y}`),
            },
        },
    },
    scales: { y: { beginAtZero: true } },
}));
</script>

<template>
    <div class="h-64"><Line :data="data" :options="options" /></div>
</template>
