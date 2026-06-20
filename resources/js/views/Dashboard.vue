<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(true);
const data = ref(null);

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatTime(dt) {
    return new Date(dt).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function loadDashboard() {
    loading.value = true;
    try {
        data.value = await apiGet('/api/reports/dashboard');
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<template>
    <div v-if="loading" class="flex items-center justify-center py-20">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>

    <div v-else-if="data" class="flex flex-col gap-6">
        <!-- Stat Cards -->
        <div class="grid grid-cols-4 gap-4">
            <div class="card mb-0">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-muted-color font-semibold text-sm">Omzet Hari Ini</span>
                    <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full w-12 h-12">
                        <i class="pi pi-chart-line text-blue-500 text-xl"></i>
                    </div>
                </div>
                <span class="text-2xl font-bold">{{ formatRp(data.today_revenue) }}</span>
                <p class="text-sm text-muted-color mt-1 m-0">{{ data.today_transactions }} transaksi</p>
            </div>

            <div class="card mb-0">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-muted-color font-semibold text-sm">Laba Hari Ini</span>
                    <div class="flex items-center justify-center bg-green-100 dark:bg-green-900/30 rounded-full w-12 h-12">
                        <i class="pi pi-wallet text-green-500 text-xl"></i>
                    </div>
                </div>
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatRp(data.today_profit) }}</span>
                <p class="text-sm text-muted-color mt-1 m-0">HPP: {{ formatRp(data.today_cost) }}</p>
            </div>

            <div class="card mb-0">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-muted-color font-semibold text-sm">Omzet Bulan Ini</span>
                    <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-900/30 rounded-full w-12 h-12">
                        <i class="pi pi-calendar text-purple-500 text-xl"></i>
                    </div>
                </div>
                <span class="text-2xl font-bold">{{ formatRp(data.month_revenue) }}</span>
            </div>

            <div class="card mb-0">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-muted-color font-semibold text-sm">Peringatan Stok</span>
                    <div class="flex items-center justify-center rounded-full w-12 h-12"
                        :class="data.low_stock.length > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30'">
                        <i class="pi text-xl" :class="data.low_stock.length > 0 ? 'pi-exclamation-triangle text-red-500' : 'pi-check-circle text-green-500'"></i>
                    </div>
                </div>
                <span class="text-2xl font-bold" :class="data.low_stock.length > 0 ? 'text-red-500' : ''">
                    {{ data.low_stock.length }} produk
                </span>
                <p class="text-sm text-muted-color mt-1 m-0">stok di bawah minimum</p>
            </div>
        </div>

        <!-- Chart & Top Products -->
        <div class="grid grid-cols-2 gap-4">
            <!-- Revenue Chart (last 7 days) -->
            <div class="card mb-0">
                <h3 class="text-lg font-semibold mt-0 mb-4">Omzet 7 Hari Terakhir</h3>
                <div class="flex items-end gap-2" style="height: 200px;">
                    <div v-for="(day, i) in data.chart_data" :key="i"
                        class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full bg-primary/20 rounded-t-md relative overflow-hidden" :style="{
                            height: Math.max(8, (day.revenue / Math.max(...data.chart_data.map(d => d.revenue), 1)) * 180) + 'px'
                        }">
                            <div class="absolute inset-0 bg-primary rounded-t-md"></div>
                        </div>
                        <span class="text-xs text-muted-color">{{ day.date }}</span>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="card mb-0">
                <h3 class="text-lg font-semibold mt-0 mb-4">Produk Terlaris Bulan Ini</h3>
                <div v-if="data.top_products.length === 0" class="text-center py-8 text-muted-color">
                    Belum ada data penjualan bulan ini.
                </div>
                <div v-else class="flex flex-col gap-2">
                    <div v-for="(p, i) in data.top_products" :key="i"
                        class="flex items-center justify-between py-2 border-b border-surface-200 dark:border-surface-700 last:border-0">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold"
                                :class="i < 3 ? 'bg-primary text-white' : 'bg-surface-200 dark:bg-surface-700'">
                                {{ i + 1 }}
                            </span>
                            <span class="text-sm">{{ p.description }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold">{{ p.total_qty }}x</span>
                            <span class="text-xs text-muted-color ml-2">{{ formatRp(p.total_revenue) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock & Recent Transactions -->
        <div class="grid grid-cols-2 gap-4">
            <!-- Low Stock -->
            <div class="card mb-0">
                <h3 class="text-lg font-semibold mt-0 mb-4">
                    <i class="pi pi-exclamation-triangle text-red-500 mr-2"></i>Peringatan Stok Rendah
                </h3>
                <div v-if="data.low_stock.length === 0" class="text-center py-6 text-green-500">
                    <i class="pi pi-check-circle text-3xl block mb-2"></i>
                    Semua stok aman.
                </div>
                <DataTable v-else :value="data.low_stock" dataKey="id" class="p-datatable-sm">
                    <Column field="name" header="Produk" />
                    <Column header="Stok" style="width: 6rem">
                        <template #body="{ data: row }">
                            <Tag :value="`${row.stock} ${row.unit}`" severity="danger" />
                        </template>
                    </Column>
                    <Column header="Min" style="width: 5rem">
                        <template #body="{ data: row }">{{ row.min_stock }}</template>
                    </Column>
                </DataTable>
            </div>

            <!-- Recent Transactions -->
            <div class="card mb-0">
                <h3 class="text-lg font-semibold mt-0 mb-4">Transaksi Terakhir</h3>
                <div v-if="data.recent_transactions.length === 0" class="text-center py-6 text-muted-color">
                    Belum ada transaksi.
                </div>
                <DataTable v-else :value="data.recent_transactions" dataKey="id" class="p-datatable-sm">
                    <Column field="invoice_number" header="Invoice" style="width: 10rem" />
                    <Column header="Kasir">
                        <template #body="{ data: row }">{{ row.user?.name }}</template>
                    </Column>
                    <Column header="Total">
                        <template #body="{ data: row }">{{ formatRp(row.total) }}</template>
                    </Column>
                    <Column header="Metode" style="width: 5rem">
                        <template #body="{ data: row }">
                            <Tag :value="row.payment_method.toUpperCase()" :severity="row.payment_method === 'cash' ? 'success' : 'info'" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
