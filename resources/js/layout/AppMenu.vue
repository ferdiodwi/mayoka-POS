<script setup>
import { computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import AppMenuItem from './AppMenuItem.vue';

const { isOwner } = useAuth();

const ownerMenu = [
    {
        label: 'Menu Utama',
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
            { label: 'Point of Sale', icon: 'pi pi-fw pi-shopping-cart', to: '/pos' },
        ]
    },
    {
        label: 'Master Data',
        items: [
            { label: 'Kategori', icon: 'pi pi-fw pi-tag', to: '/categories' },
            { label: 'Produk', icon: 'pi pi-fw pi-box', to: '/products' },
            { label: 'Pelanggan', icon: 'pi pi-fw pi-users', to: '/customers' },
            { label: 'Harga Cetak', icon: 'pi pi-fw pi-print', to: '/print-prices' },
            { label: 'Jasa Tambahan', icon: 'pi pi-fw pi-plus-circle', to: '/addon-services' },
        ]
    },
    {
        label: 'Keuangan',
        items: [
            { label: 'Pembelian Barang', icon: 'pi pi-fw pi-truck', to: '/purchases' },
            { label: 'Pengeluaran', icon: 'pi pi-fw pi-credit-card', to: '/expenses' },
            { label: 'Laba Rugi', icon: 'pi pi-fw pi-chart-line', to: '/reports/profit-loss' },
            { label: 'Arus Kas (Cash Flow)', icon: 'pi pi-fw pi-wallet', to: '/reports/cash-flow' },
        ]
    },
    {
        label: 'Laporan',
        items: [
            { label: 'Riwayat Transaksi', icon: 'pi pi-fw pi-history', to: '/transactions' },
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
            { label: 'Point of Sale', icon: 'pi pi-fw pi-shopping-cart', to: '/pos' },
            { label: 'Riwayat Transaksi', icon: 'pi pi-fw pi-history', to: '/transactions' },
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
