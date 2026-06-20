<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(false);
const shifts = ref([]);
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
        const data = await apiGet(`/api/reports/shifts?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`);
        shifts.value = data.shifts;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchReport);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Laporan Shift</h2>
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

        <DataTable :value="shifts" :loading="loading" stripedRows dataKey="id" emptyMessage="Tidak ada data shift.">
            <Column field="cashier" header="Kasir" sortable />
            <Column field="started_at" header="Waktu Buka" sortable style="width: 9rem" />
            <Column field="ended_at" header="Waktu Tutup" sortable style="width: 9rem">
                <template #body="{ data }">{{ data.ended_at || '—' }}</template>
            </Column>
            <Column header="Modal Awal" style="width: 8rem">
                <template #body="{ data }">{{ formatRp(data.cash_start) }}</template>
            </Column>
            <Column header="Uang Akhir" style="width: 8rem">
                <template #body="{ data }">{{ data.status === 'closed' ? formatRp(data.cash_end) : '—' }}</template>
            </Column>
            <Column header="Transaksi" style="width: 6rem">
                <template #body="{ data }">{{ data.tx_count }} ({{ formatRp(data.tx_total) }})</template>
            </Column>
            <Column header="Selisih" sortable sortField="cash_difference" style="width: 7rem">
                <template #body="{ data }">
                    <span v-if="data.status === 'closed'" :class="{
                        'text-green-600 dark:text-green-400': data.cash_difference >= 0,
                        'text-red-500 font-bold': data.cash_difference < 0
                    }">
                        {{ data.cash_difference >= 0 ? '+' : '' }}{{ formatRp(data.cash_difference) }}
                    </span>
                    <span v-else class="text-muted-color">—</span>
                </template>
            </Column>
            <Column header="Status" style="width: 5rem">
                <template #body="{ data }">
                    <Tag :value="data.status === 'open' ? 'Buka' : 'Tutup'"
                        :severity="data.status === 'open' ? 'success' : 'secondary'" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
