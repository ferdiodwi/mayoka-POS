<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(false);
const report = ref([]);
const totals = ref({});
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

async function fetchReport() {
    loading.value = true;
    try {
        const data = await apiGet(`/api/reports/sales?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`);
        report.value = data.daily;
        totals.value = data.totals;
    } finally {
        loading.value = false;
    }
}

function exportReport(format) {
    const url = `/api/reports/sales/export?format=${format}&date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`;
    window.open(url, '_blank');
}

onMounted(fetchReport);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Laporan Penjualan</h2>
        </div>

        <!-- Filters -->
        <div class="flex items-end gap-4 mb-6">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Dari Tanggal</label>
                <DatePicker v-model="dateFrom" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Sampai Tanggal</label>
                <DatePicker v-model="dateTo" dateFormat="dd/mm/yy" showIcon />
            </div>
            <Button label="Tampilkan" icon="pi pi-search" @click="fetchReport" />
            <div class="ml-auto flex gap-2">
                <Button label="Excel" icon="pi pi-file-excel" severity="success" outlined @click="exportReport('excel')" />
                <Button label="PDF" icon="pi pi-file-pdf" severity="danger" outlined @click="exportReport('pdf')" />
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-4 gap-4 mb-6" v-if="totals.revenue !== undefined">
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                <p class="text-sm text-muted-color m-0">Total Transaksi</p>
                <p class="text-2xl font-bold m-0 mt-1">{{ totals.tx_count }}</p>
            </div>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                <p class="text-sm text-muted-color m-0">Total Omzet</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400 m-0 mt-1">{{ formatRp(totals.revenue) }}</p>
            </div>
            <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center">
                <p class="text-sm text-muted-color m-0">Total HPP</p>
                <p class="text-2xl font-bold m-0 mt-1">{{ formatRp(totals.cost) }}</p>
            </div>
            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">
                <p class="text-sm text-muted-color m-0">Total Laba</p>
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 m-0 mt-1">{{ formatRp(totals.profit) }}</p>
            </div>
        </div>

        <DataTable :value="report" :loading="loading" stripedRows dataKey="date" emptyMessage="Tidak ada data untuk periode ini.">
            <Column header="Tanggal">
                <template #body="{ data }">{{ formatDate(data.date) }}</template>
            </Column>
            <Column field="tx_count" header="Jumlah Transaksi" sortable style="width: 10rem" />
            <Column header="Omzet" sortable sortField="revenue">
                <template #body="{ data }">{{ formatRp(data.revenue) }}</template>
            </Column>
            <Column header="HPP" sortable sortField="cost">
                <template #body="{ data }">{{ formatRp(data.cost) }}</template>
            </Column>
            <Column header="Laba" sortable sortField="profit">
                <template #body="{ data }">
                    <span :class="data.profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500'" class="font-semibold">
                        {{ formatRp(data.profit) }}
                    </span>
                </template>
            </Column>
            <Column header="Rata-rata/Trx" sortable sortField="avg_per_tx">
                <template #body="{ data }">{{ formatRp(data.avg_per_tx) }}</template>
            </Column>
            <Column header="Per Metode Bayar">
                <template #body="{ data }">
                    <div v-if="data.by_method" class="flex flex-wrap gap-1">
                        <Tag v-if="data.by_method.cash" :value="`Cash ${formatRp(data.by_method.cash.total)}`" severity="success" class="text-xs" />
                        <Tag v-if="data.by_method.qris" :value="`QRIS ${formatRp(data.by_method.qris.total)}`" severity="info" class="text-xs" />
                        <Tag v-if="data.by_method.transfer" :value="`Transfer ${formatRp(data.by_method.transfer.total)}`" severity="warn" class="text-xs" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
