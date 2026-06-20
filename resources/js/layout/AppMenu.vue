<script setup>
import { computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import AppMenuItem from './AppMenuItem.vue';

const { isOwner } = useAuth();

const ownerMenu = [
    {
        label: 'Menu Utama',
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' }
        ]
    },
    {
        label: 'Master Data',
        items: [
            { label: 'Kategori', icon: 'pi pi-fw pi-tag', to: '/categories' },
            { label: 'Produk', icon: 'pi pi-fw pi-box', to: '/products' },
            { label: 'Harga Cetak', icon: 'pi pi-fw pi-print', to: '/print-prices' },
            { label: 'Jasa Tambahan', icon: 'pi pi-fw pi-plus-circle', to: '/addon-services' },
        ]
    },
    {
        label: 'Laporan',
        items: [
            { label: 'Laporan Penjualan', icon: 'pi pi-fw pi-chart-bar', to: '/reports/sales' },
            { label: 'Laporan Kasir', icon: 'pi pi-fw pi-users', to: '/reports/cashier' },
            { label: 'Laporan Shift', icon: 'pi pi-fw pi-clock', to: '/reports/shifts' },
            { label: 'Rekap Stok', icon: 'pi pi-fw pi-warehouse', to: '/reports/stock' },
        ]
    },
    {
        label: 'Manajemen',
        items: [
            { label: 'Manajemen User', icon: 'pi pi-fw pi-user-edit', to: '/users' },
        ]
    },
];

const kasirMenu = [
    {
        label: 'Kasir',
        items: [
            { label: 'Point of Sale', icon: 'pi pi-fw pi-shopping-cart', to: '/pos' }
        ]
    }
];

const model = computed(() => isOwner.value ? ownerMenu : kasirMenu);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
