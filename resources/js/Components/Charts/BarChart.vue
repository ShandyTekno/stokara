<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Tooltip, Legend, BarElement, LinearScale, CategoryScale } from 'chart.js';

ChartJS.register(Tooltip, Legend, BarElement, LinearScale, CategoryScale);

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
});

const data = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((d) => ({ borderRadius: 4, ...d })),
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true } },
};
</script>

<template>
    <div class="h-64"><Bar :data="data" :options="options" /></div>
</template>
