<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(true);
const data = ref(null);
const chartFilter = ref('week');
const chartDataValues = ref({});
const chartOptions = ref({});

const filterOptions = ref([
    { label: '7 Hari', value: 'week' },
    { label: '30 Hari', value: 'month' }
]);

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatHierarchicalStock(totalBaseQty, units) {
    if (!units || units.length === 0) return totalBaseQty;
    const sortedUnits = [...units].sort((a, b) => b.base_multiplier - a.base_multiplier);
    let remaining = totalBaseQty;
    let parts = [];
    for (const u of sortedUnits) {
        if (!u.unit_name || u.base_multiplier <= 0) continue;
        const qty = Math.floor(Math.abs(remaining) / u.base_multiplier);
        if (qty > 0) {
            parts.push(`${qty} ${u.unit_name}`);
            remaining = Math.abs(remaining) % u.base_multiplier;
        }
    }
    const prefix = totalBaseQty < 0 ? '-' : '';
    if (parts.length === 0) {
        const base = sortedUnits.find(u => u.level === 1);
        return `${prefix}0 ${base ? base.unit_name : ''}`;
    }
    return prefix + parts.join(' ');
}

function formatTime(dt) {
    return new Date(dt).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function setChartData() {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--p-text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--p-text-muted-color');
    const surfaceBorder = documentStyle.getPropertyValue('--p-content-border-color');

    chartDataValues.value = {
        labels: data.value.chart_data.map(d => d.date),
        datasets: [
            {
                label: 'Omzet Bersih',
                data: data.value.chart_data.map(d => d.revenue),
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                borderRadius: 4,
            }
        ]
    };

    chartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return formatRp(context.raw);
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: textColorSecondary, maxRotation: 45, minRotation: 0 },
                grid: { display: false }
            },
            y: {
                ticks: {
                    color: textColorSecondary,
                    callback: function(value) {
                        return value >= 1000000 ? (value / 1000000) + 'M' : value >= 1000 ? (value / 1000) + 'k' : value;
                    }
                },
                grid: { color: surfaceBorder }
            }
        }
    };
}

async function loadDashboard(showLoading = true) {
    if (showLoading) loading.value = true;
    try {
        data.value = await apiGet(`/api/reports/dashboard?chart_filter=${chartFilter.value}`);
        setChartData();
    } finally {
        if (showLoading) loading.value = false;
    }
}

onMounted(() => {
    loadDashboard();
    
    if (window.Echo) {
        window.Echo.channel('dashboard-channel')
            .listen('DashboardUpdated', () => {
                loadDashboard(false); // reload quietly without full loading spinner
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('dashboard-channel');
    }
});
</script>

<template>
    <div v-if="loading" class="flex items-center justify-center py-20">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>

    <div v-else-if="data" class="flex flex-col gap-6">
        <!-- Stat Cards -->
        <div class="grid grid-cols-4 gap-4">
            <div class="card mb-0 flex flex-col h-full justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-semibold text-sm">Omzet Hari Ini</span>
                        <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full w-12 h-12">
                            <i class="pi pi-chart-line text-blue-500 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-2xl font-bold">{{ formatRp(data.today_revenue) }}</span>
                </div>
                <p class="text-sm text-muted-color mt-3 m-0">{{ data.today_transactions }} transaksi</p>
            </div>

            <div class="card mb-0 flex flex-col h-full justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-semibold text-sm">Laba Hari Ini</span>
                        <div class="flex items-center justify-center bg-green-100 dark:bg-green-900/30 rounded-full w-12 h-12">
                            <i class="pi pi-wallet text-green-500 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatRp(data.today_profit) }}</span>
                </div>
                <p class="text-sm text-muted-color mt-3 m-0">HPP: {{ formatRp(data.today_cost) }}</p>
            </div>

            <div class="card mb-0 flex flex-col h-full justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-semibold text-sm">Omzet Bulan Ini</span>
                        <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-900/30 rounded-full w-12 h-12">
                            <i class="pi pi-calendar text-purple-500 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-2xl font-bold">{{ formatRp(data.month_revenue) }}</span>
                </div>
                <p class="text-sm text-transparent mt-3 m-0">_</p>
            </div>

            <div class="card mb-0 flex flex-col h-full justify-between">
                <div>
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
                </div>
                <p class="text-sm text-muted-color mt-3 m-0">stok di bawah minimum</p>
            </div>
        </div>

        <!-- Chart & Top Products -->
        <div class="grid grid-cols-2 gap-4">
            <!-- Revenue Chart -->
            <div class="card mb-0 flex flex-col h-full">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold m-0">Grafik Omzet</h3>
                    <SelectButton v-model="chartFilter" :options="filterOptions" optionLabel="label" optionValue="value" @change="loadDashboard" />
                </div>
                <div class="flex-1 relative min-h-[200px]">
                    <div class="absolute inset-0">
                        <Chart type="bar" :data="chartDataValues" :options="chartOptions" class="h-full w-full" />
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="card mb-0 flex flex-col h-full">
                <h3 class="text-lg font-semibold mt-0 mb-4">Produk Terlaris Bulan Ini</h3>
                <div v-if="data.top_products.length === 0" class="text-center py-8 text-muted-color">
                    Belum ada data penjualan bulan ini.
                </div>
                <div v-else class="flex flex-col gap-2 flex-1 overflow-y-auto pr-2" style="max-height: 215px;">
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
            <div class="card mb-0 flex flex-col h-full">
                <h3 class="text-lg font-semibold mt-0 mb-4">
                    <i class="pi pi-exclamation-triangle text-red-500 mr-2"></i>Peringatan Stok Rendah
                </h3>
                <div v-if="data.low_stock.length === 0" class="text-center py-6 text-green-500">
                    <i class="pi pi-check-circle text-3xl block mb-2"></i>
                    Semua stok aman.
                </div>
                <DataTable v-else :value="data.low_stock" dataKey="id" class="p-datatable-sm" scrollable scrollHeight="300px">
                    <Column field="name" header="Produk" />
                    <Column header="Stok" style="width: 6rem">
                        <template #body="{ data: row }">
                            <Tag :value="formatHierarchicalStock(row.stock, row.units)" severity="danger" />
                        </template>
                    </Column>
                    <Column header="Min" style="width: 5rem">
                        <template #body="{ data: row }">{{ row.min_stock }}</template>
                    </Column>
                </DataTable>
            </div>

            <!-- Recent Transactions -->
            <div class="card mb-0 flex flex-col h-full">
                <h3 class="text-lg font-semibold mt-0 mb-4">Transaksi Terakhir</h3>
                <div v-if="data.recent_transactions.length === 0" class="text-center py-6 text-muted-color">
                    Belum ada transaksi.
                </div>
                <DataTable v-else :value="data.recent_transactions" dataKey="id" class="p-datatable-sm" scrollable scrollHeight="300px">
                    <Column field="invoice_number" header="Invoice" style="width: 10rem" />
                    <Column header="Kasir">
                        <template #body="{ data: row }">{{ row.user?.name }}</template>
                    </Column>
                    <Column header="Total">
                        <template #body="{ data: row }">{{ formatRp(row.total) }}</template>
                    </Column>
                    <Column header="Metode" style="width: 5rem">
                        <template #body="{ data: row }">
                            <Tag :value="row.payment_method.toUpperCase()" :severity="row.payment_method === 'cash' ? 'success' : row.payment_method === 'qris' ? 'info' : 'warn'" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
