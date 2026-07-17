<script setup>
import { computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import AppMenuItem from './AppMenuItem.vue';

const { hasPermission, isOwner } = useAuth();

const rawMenu = [
    {
        label: 'Menu Utama',
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/', permission: 'reports.read' },
            { label: 'Point of Sale', icon: 'pi pi-fw pi-shopping-cart', to: '/pos' },
        ]
    },
    {
        label: 'Master Data',
        items: [
            { label: 'Kategori', icon: 'pi pi-fw pi-tag', to: '/categories', permission: 'categories.read' },
            { label: 'Produk', icon: 'pi pi-fw pi-box', to: '/products', permission: 'products.read' },
            { label: 'Pelanggan', icon: 'pi pi-fw pi-users', to: '/customers', permission: 'customers.read' },
            { label: 'Harga Cetak', icon: 'pi pi-fw pi-print', to: '/print-prices', permission: 'print_prices.read' },
            { label: 'Jasa Tambahan', icon: 'pi pi-fw pi-plus-circle', to: '/addon-services', permission: 'addons.read' },
            { label: 'Cetak Label Harga', icon: 'pi pi-fw pi-tag', to: '/price-labels', permission: 'products.read' },
        ]
    },
    {
        label: 'Keuangan',
        items: [
            { label: 'Data Supplier', icon: 'pi pi-fw pi-truck', to: '/suppliers', permission: 'purchases.read' },
            { label: 'Pembelian Barang', icon: 'pi pi-fw pi-shopping-bag', to: '/purchases', permission: 'purchases.read' },
            { label: 'Stok Opname', icon: 'pi pi-fw pi-check-square', to: '/stock-opname', permission: 'purchases.read' },
            { label: 'Pengeluaran', icon: 'pi pi-fw pi-credit-card', to: '/expenses', permission: 'expenses.read' },
            { label: 'Laba Rugi', icon: 'pi pi-fw pi-chart-line', to: '/reports/profit-loss', permission: 'reports.read' },
            { label: 'Arus Kas (Cash Flow)', icon: 'pi pi-fw pi-wallet', to: '/reports/cash-flow', permission: 'reports.read' },
        ]
    },
    {
        label: 'Laporan',
        items: [
            { label: 'Riwayat Transaksi', icon: 'pi pi-fw pi-history', to: '/transactions' },
            { label: 'Laporan Penjualan', icon: 'pi pi-fw pi-chart-bar', to: '/reports/sales', permission: 'reports.read' },
            { label: 'Laporan Kasir', icon: 'pi pi-fw pi-users', to: '/reports/cashier', permission: 'reports.read' },
            { label: 'Laporan Shift', icon: 'pi pi-fw pi-clock', to: '/reports/shifts', permission: 'reports.read' },
            { label: 'Rekap Stok', icon: 'pi pi-fw pi-warehouse', to: '/reports/stock', permission: 'reports.read' },
        ]
    },
    {
        label: 'Manajemen',
        items: [
            { label: 'Manajemen User', icon: 'pi pi-fw pi-user-edit', to: '/users', permission: 'users.read' },
            { label: 'Manajemen Cabang', icon: 'pi pi-fw pi-building', to: '/branches', ownerOnly: true },
        ]
    },
];

const model = computed(() => {
    return rawMenu.map(group => {
        return {
            ...group,
            items: group.items.filter(item => {
                if (item.ownerOnly && !isOwner.value) return false;
                return !item.permission || hasPermission(item.permission);
            })
        };
    }).filter(group => group.items.length > 0);
});
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
