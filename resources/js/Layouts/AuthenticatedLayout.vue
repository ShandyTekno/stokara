<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/NavIcon.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});
const isAdmin = computed(() => user.value.role === 'admin');
const sidebarOpen = ref(false);

// Menu otomatis aktif begitu route-nya ada (dibuat di tahap berikutnya).
const menu = [
    { label: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'home' },
    { label: 'Data Barang', route: 'products.index', pattern: 'products.*', icon: 'box' },
    { label: 'Kategori', route: 'categories.index', pattern: 'categories.*', icon: 'tag', adminOnly: true },
    { label: 'Pemasok', route: 'suppliers.index', pattern: 'suppliers.*', icon: 'users', adminOnly: true },
    { label: 'Barang Masuk', route: 'stock-ins.index', pattern: 'stock-ins.*', icon: 'download' },
    { label: 'Barang Keluar', route: 'stock-outs.index', pattern: 'stock-outs.*', icon: 'upload' },
    { label: 'Penjualan', route: 'sales.index', pattern: 'sales.*', icon: 'cart' },
    { label: 'Monitoring Stok', route: 'monitoring.index', pattern: 'monitoring.*', icon: 'chart' },
    { label: 'AI Assistant', route: 'ai.assistant', pattern: 'ai.assistant*', icon: 'chat', adminOnly: true },
    { label: 'Analisis AI', route: 'ai.analysis', pattern: 'ai.analysis*', icon: 'sparkles', adminOnly: true },
    { label: 'Laporan', route: 'reports.index', pattern: 'reports.*', icon: 'document', adminOnly: true },
    { label: 'Pengguna', route: 'users.index', pattern: 'users.*', icon: 'users', adminOnly: true },
    { label: 'Aktivitas', route: 'activities.index', pattern: 'activities.*', icon: 'clock', adminOnly: true },
];

const visibleMenu = computed(() =>
    menu
        .filter((i) => !i.adminOnly || isAdmin.value)
        .map((i) => {
            const ready = !i.soon && route().has(i.route);
            return { ...i, ready, active: ready && route().current(i.pattern) };
        }),
);

const roleLabel = computed(() => (isAdmin.value ? 'Admin / Pemilik' : 'Karyawan'));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false" />

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-blue-900 text-white transition-transform duration-200 lg:translate-x-0 print:hidden',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10">
                    <NavIcon name="box" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="font-semibold leading-tight">StokCerdas AI</p>
                    <p class="truncate text-xs text-blue-200">{{ user.store?.name }}</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="item in visibleMenu" :key="item.route">
                    <Link
                        v-if="item.ready"
                        :href="route(item.route)"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                            item.active ? 'bg-white/15 text-white' : 'text-blue-100 hover:bg-white/10',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <NavIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                        {{ item.label }}
                    </Link>
                    <div
                        v-else
                        class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-blue-300/60"
                        title="Tersedia di tahap berikutnya"
                    >
                        <NavIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                        {{ item.label }}
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase">Segera</span>
                    </div>
                </template>
            </nav>

            <div class="border-t border-white/10 px-5 py-4 text-xs text-blue-200">
                <p class="truncate font-medium text-white">{{ user.name }}</p>
                <p>{{ roleLabel }}</p>
            </div>
        </aside>

        <div class="lg:pl-64 print:pl-0">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b bg-white px-4 sm:px-6 print:hidden">
                <button type="button" class="rounded-md p-2 text-gray-600 hover:bg-gray-100 lg:hidden" @click="sidebarOpen = true">
                    <NavIcon name="menu" class="h-6 w-6" />
                </button>

                <div class="min-w-0 flex-1 truncate"><slot name="header" /></div>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-900 text-sm font-semibold text-white">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="hidden sm:block">{{ user.name }}</span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink>
                    </template>
                </Dropdown>
            </header>

            <main class="p-4 sm:p-6">
                <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
