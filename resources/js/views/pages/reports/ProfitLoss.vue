<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(false);
const data = ref(null);
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

function profitMargin() {
    if (!data.value || data.value.revenue === 0) return '0%';
    return ((data.value.net_profit / data.value.revenue) * 100).toFixed(1) + '%';
}

async function fetchReport() {
    loading.value = true;
    try {
        data.value = await apiGet(`/api/reports/profit-loss?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`);
    } finally {
        loading.value = false;
    }
}

onMounted(fetchReport);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Laporan Laba Rugi</h2>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4 mb-6">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Dari Tanggal</label>
                <DatePicker v-model="dateFrom" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Sampai Tanggal</label>
                <DatePicker v-model="dateTo" dateFormat="dd/mm/yy" showIcon />
            </div>
            <Button label="Tampilkan" icon="pi pi-search" @click="fetchReport" />
        </div>

        <div v-if="loading" class="flex items-center justify-center py-20">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <div v-else-if="data" class="flex flex-col gap-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl text-center">
                    <p class="text-sm text-muted-color m-0">Total Pendapatan</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 m-0 mt-1">{{ formatRp(data.revenue) }}</p>
                    <p class="text-xs text-muted-color m-0 mt-1">{{ data.tx_count }} transaksi</p>
                </div>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-xl text-center">
                    <p class="text-sm text-muted-color m-0">Laba Kotor</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 m-0 mt-1">{{ formatRp(data.gross_profit) }}</p>
                    <p class="text-xs text-muted-color m-0 mt-1">Pendapatan - HPP</p>
                </div>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-xl text-center">
                    <p class="text-sm text-muted-color m-0">Total Pengeluaran</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 m-0 mt-1">{{ formatRp(data.total_expenses) }}</p>
                </div>
                <div class="p-4 rounded-xl text-center"
                    :class="data.net_profit >= 0
                        ? 'bg-emerald-50 dark:bg-emerald-900/20'
                        : 'bg-red-100 dark:bg-red-900/30'">
                    <p class="text-sm text-muted-color m-0">Laba Bersih</p>
                    <p class="text-3xl font-bold m-0 mt-1"
                        :class="data.net_profit >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-red-600 dark:text-red-400'">
                        {{ formatRp(data.net_profit) }}
                    </p>
                    <p class="text-xs text-muted-color m-0 mt-1">Margin: {{ profitMargin() }}</p>
                </div>
            </div>

            <!-- P&L Statement -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Statement -->
                <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                    <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold text-lg">
                        Laporan Laba Rugi
                    </div>

                    <!-- Revenue -->
                    <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                <i class="pi pi-arrow-up mr-2"></i>Pendapatan Penjualan (Kotor)
                            </span>
                            <span class="font-bold text-lg">{{ formatRp(data.gross_revenue) }}</span>
                        </div>
                    </div>

                    <!-- Returns -->
                    <div v-if="data.returned_revenue > 0" class="px-4 py-2 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-color pl-4">
                                <i class="pi pi-replay mr-1"></i>Retur Penjualan
                            </span>
                            <span class="text-red-500">- {{ formatRp(data.returned_revenue) }}</span>
                        </div>
                    </div>

                    <!-- Net Revenue -->
                    <div class="px-4 py-2 border-b border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                = Pendapatan Bersih
                            </span>
                            <span class="font-bold text-blue-600 dark:text-blue-400">{{ formatRp(data.revenue) }}</span>
                        </div>
                    </div>

                    <!-- HPP -->
                    <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-color">
                                <i class="pi pi-minus mr-2"></i>Harga Pokok Penjualan (HPP)
                            </span>
                            <span class="text-red-500">- {{ formatRp(data.hpp) }}</span>
                        </div>
                    </div>

                    <!-- Gross Profit -->
                    <div class="px-4 py-3 border-b-2 border-surface-300 dark:border-surface-600">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-green-600 dark:text-green-400">
                                = Laba Kotor
                            </span>
                            <span class="font-bold text-green-600 dark:text-green-400">{{ formatRp(data.gross_profit) }}</span>
                        </div>
                    </div>

                    <!-- Expenses Breakdown -->
                    <div v-for="(amount, cat) in data.expenses_by_category" :key="cat"
                        class="px-4 py-2 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-color pl-4">
                                {{ data.category_labels[cat] || cat }}
                            </span>
                            <span class="text-red-500">- {{ formatRp(amount) }}</span>
                        </div>
                    </div>
                    <div v-if="data.total_expenses > 0"
                        class="px-4 py-2 border-b border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-color font-semibold">
                                <i class="pi pi-minus mr-2"></i>Total Pengeluaran Operasional
                            </span>
                            <span class="text-red-500 font-semibold">- {{ formatRp(data.total_expenses) }}</span>
                        </div>
                    </div>

                    <!-- Net Profit -->
                    <div class="px-4 py-4"
                        :class="data.net_profit >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-lg">= LABA BERSIH</span>
                            <span class="font-bold text-2xl"
                                :class="data.net_profit >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-red-600 dark:text-red-400'">
                                {{ formatRp(data.net_profit) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Revenue by Method + Purchases -->
                <div class="flex flex-col gap-4">
                    <!-- Revenue by Method -->
                    <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                        <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold">
                            Pendapatan per Metode Bayar
                        </div>
                        <div v-if="Object.keys(data.revenue_by_method).length === 0"
                            class="p-4 text-center text-muted-color">Belum ada data.</div>
                        <div v-else>
                            <div v-for="(info, method) in data.revenue_by_method" :key="method"
                                class="px-4 py-3 flex justify-between items-center border-b border-surface-200 dark:border-surface-700 last:border-0">
                                <div class="flex items-center gap-2">
                                    <Tag :value="method.toUpperCase()"
                                        :severity="method === 'cash' ? 'success' : method === 'qris' ? 'info' : 'warn'" />
                                    <span class="text-sm text-muted-color">{{ info.count }} transaksi</span>
                                </div>
                                <span class="font-semibold">{{ formatRp(info.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Purchases Summary -->
                    <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                        <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold">
                            Ringkasan Pembelian Barang
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Jumlah Pembelian</span>
                                <span class="font-semibold">{{ data.purchase_count }} kali</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-muted-color">Total Pembelian</span>
                                <span class="font-bold text-lg text-orange-600 dark:text-orange-400">{{ formatRp(data.total_purchases) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-xl">
                        <p class="m-0 text-sm text-muted-color">
                            <i class="pi pi-info-circle mr-1"></i>
                            <strong>Laba Bersih</strong> dihitung dari: Pendapatan - HPP - Pengeluaran Operasional.
                            Pembelian barang tidak langsung mengurangi laba karena sudah terhitung dalam HPP saat produk terjual.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
