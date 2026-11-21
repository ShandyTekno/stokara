<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { activeTheme, useTheme } from '@/composables/useTheme';

useTheme();

const page = usePage();
const user = computed(() => page.props.auth.user);
const site = computed(() => page.props.site ?? {});
const flash = computed(() => page.props.flash ?? {});
const isAdmin = computed(() => user.value?.role === 'admin');
const sidebarOpen = ref(false);
const logoFailed = ref(false);

const sidebarStyle = computed(() => activeTheme.value?.sidebar_style ?? site.value?.settings?.sidebar_style ?? 'dark');
const isLightSidebar = computed(() => sidebarStyle.value === 'light');

const sidebarBgClass = computed(() => {
    if (sidebarStyle.value === 'light') {
        return 'sidebar-light border-slate-200 text-slate-800';
    }
    if (sidebarStyle.value === 'colored') {
        return 'sidebar-colored border-white/10 text-white';
    }
    return 'sidebar-dark border-slate-800/50 text-white';
});

const menu = [
    { label: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'home' },
    { label: 'Data Barang', route: 'products.index', pattern: 'products.*', icon: 'box' },
    { label: 'Kategori', route: 'categories.index', pattern: 'categories.*', icon: 'tag', adminOnly: true },
    { label: 'Pemasok', route: 'suppliers.index', pattern: 'suppliers.*', icon: 'users', adminOnly: true },
    { label: 'Barang Masuk', route: 'stock-ins.index', pattern: 'stock-ins.*', icon: 'download' },
    { label: 'Barang Keluar', route: 'stock-outs.index', pattern: 'stock-outs.*', icon: 'upload' },
    { label: 'Penjualan (Kasir)', route: 'sales.index', pattern: 'sales.*', icon: 'cart' },
    { label: 'Monitoring Stok', route: 'monitoring.index', pattern: 'monitoring.*', icon: 'chart' },
    { label: 'AI Assistant', route: 'ai.assistant', pattern: 'ai.assistant*', icon: 'chat', adminOnly: true },
    { label: 'Analisis AI', route: 'ai.analysis', pattern: 'ai.analysis*', icon: 'sparkles', adminOnly: true },
    { label: 'Laporan', route: 'reports.index', pattern: 'reports.*', icon: 'document', adminOnly: true },
    { label: 'Pengguna', route: 'users.index', pattern: 'users.*', icon: 'users', adminOnly: true },
    { label: 'Aktivitas', route: 'activities.index', pattern: 'activities.*', icon: 'clock', adminOnly: true },
    { label: 'Pengaturan', route: 'settings.edit', pattern: 'settings.*', icon: 'cog', adminOnly: true },
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
    <div class="min-h-screen bg-slate-50/70 antialiased">
        <!-- Backdrop mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-xs transition-opacity lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sleek modern sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r shadow-xl transition-all duration-300 ease-out lg:translate-x-0 print:hidden',
                sidebarBgClass,
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo Section -->
            <div
                :class="[
                    'flex h-16 items-center gap-3 border-b px-5',
                    isLightSidebar ? 'border-slate-100' : 'border-white/10',
                ]"
            >
                <div
                    v-if="site.logo_url && !logoFailed"
                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-xs ring-1 ring-slate-200/70"
                >
                    <img
                        :src="site.logo_url"
                        :alt="site.app_name"
                        class="h-full w-full object-contain"
                        @error="logoFailed = true"
                    />
                </div>
                <div
                    v-else
                    :class="[
                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-xs',
                        isLightSidebar
                            ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-200'
                            : 'bg-white/15 text-blue-300 ring-1 ring-white/20',
                    ]"
                >
                    <NavIcon name="box" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p
                        :class="[
                            'truncate font-bold tracking-wide leading-tight',
                            isLightSidebar ? 'text-slate-900' : 'text-white',
                        ]"
                    >
                        {{ site.app_name || 'STOKARA' }}
                    </p>
                    <p
                        :class="[
                            'truncate text-xs',
                            isLightSidebar ? 'text-slate-500' : 'text-blue-300/85',
                        ]"
                    >
                        {{ site.store_name || user?.store?.name || 'Toko' }}
                    </p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="item in visibleMenu" :key="item.route">
                    <Link
                        v-if="item.ready"
                        :href="route(item.route)"
                        :class="[
                            'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                            item.active
                                ? isLightSidebar
                                    ? 'bg-blue-50 font-semibold text-blue-700 shadow-xs before:absolute before:left-0 before:top-2.5 before:bottom-2.5 before:w-1 before:rounded-r-full before:bg-blue-600'
                                    : 'bg-white/15 font-semibold text-white shadow-xs backdrop-blur-xs before:absolute before:left-0 before:top-2.5 before:bottom-2.5 before:w-1 before:rounded-r-full before:bg-white'
                                : isLightSidebar
                                    ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 hover:translate-x-1'
                                    : 'text-white/75 hover:bg-white/10 hover:text-white hover:translate-x-1',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <NavIcon
                            :name="item.icon"
                            :class="[
                                'h-5 w-5 shrink-0 transition-transform duration-200 group-hover:scale-110',
                                item.active
                                    ? isLightSidebar
                                        ? 'text-blue-700'
                                        : 'text-white'
                                    : isLightSidebar
                                        ? 'text-slate-400 group-hover:text-blue-600'
                                        : 'text-white/60 group-hover:text-white',
                            ]"
                        />
                        <span>{{ item.label }}</span>
                    </Link>
                    <div
                        v-else
                        class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500"
                        title="Tersedia di tahap berikutnya"
                    >
                        <NavIcon :name="item.icon" class="h-5 w-5 shrink-0 opacity-40" />
                        <span>{{ item.label }}</span>
                        <span class="ml-auto rounded-md bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-400">
                            Segera
                        </span>
                    </div>
                </template>
            </nav>

            <!-- Bottom User Card in Sidebar -->
            <div :class="['border-t p-3', isLightSidebar ? 'border-slate-100' : 'border-white/10']">
                <div
                    :class="[
                        'flex items-center gap-3 rounded-xl p-2.5',
                        isLightSidebar ? 'bg-slate-50 border border-slate-100' : 'bg-white/10',
                    ]"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-sm font-bold text-white shadow-xs">
                        {{ user?.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            :class="[
                                'truncate text-xs font-semibold',
                                isLightSidebar ? 'text-slate-800' : 'text-white',
                            ]"
                        >
                            {{ user?.name }}
                        </p>
                        <span
                            :class="[
                                'inline-flex items-center gap-1 text-[11px]',
                                isLightSidebar ? 'text-slate-500' : 'text-white/75',
                            ]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            {{ roleLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="lg:pl-64 print:pl-0">
            <!-- Modern Glass Header -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/80 backdrop-blur-md px-4 sm:px-6 shadow-xs print:hidden">
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:hidden transition-colors"
                        @click="sidebarOpen = true"
                    >
                        <NavIcon name="menu" class="h-6 w-6" />
                    </button>
                    <div class="min-w-0 truncate">
                        <slot name="header" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex items-center gap-2.5 rounded-full border border-slate-200/80 bg-white py-1 pl-1.5 pr-3 text-sm text-slate-700 shadow-xs transition-all hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-tr from-blue-700 to-indigo-600 text-xs font-bold text-white">
                                    {{ user?.name?.charAt(0).toUpperCase() }}
                                </span>
                                <span class="hidden font-medium sm:block">{{ user?.name }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="px-4 py-2 border-b border-slate-100 sm:hidden">
                                <p class="text-xs text-slate-400">Masuk sebagai</p>
                                <p class="truncate text-sm font-semibold text-slate-800">{{ user?.name }}</p>
                            </div>
                            <DropdownLink :href="route('profile.edit')">Profil Saya</DropdownLink>
                            <DropdownLink v-if="isAdmin" :href="route('settings.edit')">Pengaturan Website</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 sm:p-6 lg:p-8 animate-fade-in">
                <!-- Flash Messages -->
                <div
                    v-if="flash.success"
                    class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm font-medium text-emerald-800 shadow-xs"
                >
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ flash.success }}</span>
                </div>
                <div
                    v-if="flash.error"
                    class="mb-6 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-sm font-medium text-rose-800 shadow-xs"
                >
                    <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ flash.error }}</span>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
