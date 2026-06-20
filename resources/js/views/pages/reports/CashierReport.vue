<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(false);
const report = ref([]);
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

async function fetchReport() {
    loading.value = true;
    try {
        const data = await apiGet(`/api/reports/cashier?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`);
        report.value = data.report;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchReport);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Laporan Per Kasir</h2>
        </div>

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
        </div>

        <DataTable :value="report" :loading="loading" stripedRows dataKey="user_id" emptyMessage="Tidak ada data.">
            <Column field="name" header="Nama Kasir" sortable />
            <Column field="shift_count" header="Jumlah Shift" sortable style="width: 8rem" />
            <Column field="tx_count" header="Total Transaksi" sortable style="width: 9rem" />
            <Column header="Total Omzet" sortable sortField="total_revenue">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatRp(data.total_revenue) }}</span>
                </template>
            </Column>
            <Column header="Rata-rata Selisih Kas" sortable sortField="avg_cash_diff">
                <template #body="{ data }">
                    <span :class="{
                        'text-green-600 dark:text-green-400': data.avg_cash_diff >= 0,
                        'text-red-500': data.avg_cash_diff < 0
                    }">
                        {{ data.avg_cash_diff >= 0 ? '+' : '' }}{{ formatRp(data.avg_cash_diff) }}
                    </span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
