<script setup>
import { ref, onMounted } from 'vue';
import { apiGet, apiPost } from '@/composables/useApi';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const loading = ref(false);
const shifts = ref([]);
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());

const showReceipt = ref(false);
const receiptData = ref(null);
const activeShiftId = ref(null);
const printing = ref(false);

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

async function printReport(shift) {
    try {
        const res = await apiGet(`/api/shifts/${shift.id}/receipt`);
        receiptData.value = res.receipt;
        activeShiftId.value = shift.id;
        showReceipt.value = true;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal memuat laporan', detail: err.message, life: 5000 });
    }
}

async function executePrint() {
    if (!activeShiftId.value) return;
    printing.value = true;
    try {
        await apiPost(`/api/shifts/${activeShiftId.value}/print`);
        toast.add({ severity: 'success', summary: 'Cetak Berhasil', detail: 'Laporan shift terkirim ke printer.', life: 3000 });
        showReceipt.value = false;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Cetak Gagal', detail: err.message || 'Gagal terhubung ke printer', life: 5000 });
    } finally {
        printing.value = false;
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
            <Column header="Per Metode Bayar">
                <template #body="{ data }">
                    <div v-if="data.by_method" class="flex flex-wrap gap-1">
                        <Tag v-if="data.by_method.cash" :value="`Cash ${formatRp(data.by_method.cash.total)}`" severity="success" class="text-xs" />
                        <Tag v-if="data.by_method.qris" :value="`QRIS ${formatRp(data.by_method.qris.total)}`" severity="info" class="text-xs" />
                        <Tag v-if="data.by_method.transfer" :value="`Transfer ${formatRp(data.by_method.transfer.total)}`" severity="warn" class="text-xs" />
                    </div>
                </template>
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
            <Column header="Aksi" style="width: 5rem">
                <template #body="{ data }">
                    <Button icon="pi pi-print" severity="secondary" text rounded v-tooltip.top="'Cetak Laporan'" @click="printReport(data)" />
                </template>
            </Column>
        </DataTable>

        <!-- Preview Laporan Dialog -->
        <Dialog :visible="showReceipt" header="Pratinjau Laporan Kasir" modal :style="{ width: '380px' }"
            @update:visible="showReceipt = $event">
            <div v-if="receiptData" class="font-mono text-sm bg-surface-50 dark:bg-surface-900 p-4 rounded border border-surface-200 dark:border-surface-700">
                <div class="text-center mb-3">
                    <p class="font-bold text-lg m-0">LAPORAN SALDO KASIR</p>
                    <p class="text-xs text-muted-color m-0">================================</p>
                </div>
                
                <div class="mb-3 space-y-1">
                    <div class="flex justify-between"><span class="w-24">KASIR</span><span>: {{ receiptData.cashier }}</span></div>
                    <div class="flex justify-between"><span class="w-24">TANGGAL</span><span>: {{ receiptData.date }}</span></div>
                    <div class="flex justify-between"><span class="w-24">JAM</span><span>: {{ receiptData.time }}</span></div>
                </div>
                
                <hr class="my-2 border-dashed border-surface-300 dark:border-surface-600" />
                
                <div class="mb-3 space-y-1">
                    <div class="flex justify-between py-1"><span>MODAL AWAL</span><span>{{ formatRp(receiptData.cash_start) }}</span></div>
                    <div class="flex justify-between py-1"><span>PENJUALAN</span><span>{{ formatRp(receiptData.cash_sales) }} (+)</span></div>
                    <div v-if="receiptData.cash_expenses > 0" class="flex justify-between py-1"><span>PENGELUARAN</span><span>{{ formatRp(receiptData.cash_expenses) }} (-)</span></div>
                    <div v-if="receiptData.cash_refunds > 0" class="flex justify-between py-1"><span>RETUR JUAL</span><span>{{ formatRp(receiptData.cash_refunds) }} (-)</span></div>
                </div>
                
                <hr class="my-2 border-dashed border-surface-300 dark:border-surface-600" />
                
                <div class="flex justify-between py-1 font-bold text-base mb-2">
                    <span>TOTAL KAS</span><span>{{ formatRp(receiptData.cash_expected) }}</span>
                </div>
                
                <div v-if="receiptData.status === 'closed'">
                    <hr class="my-2 border-dashed border-surface-300 dark:border-surface-600" />
                    <div class="flex justify-between py-1"><span>UANG FISIK</span><span>{{ formatRp(receiptData.cash_end) }}</span></div>
                    <div class="flex justify-between py-1 font-bold">
                        <span>SELISIH</span>
                        <span :class="receiptData.cash_difference < 0 ? 'text-red-500' : 'text-green-500'">
                            {{ receiptData.cash_difference >= 0 ? '+' : '' }}{{ formatRp(receiptData.cash_difference) }}
                        </span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Batal" severity="secondary" text @click="showReceipt = false" />
                <Button label="Cetak ke Printer" icon="pi pi-print" @click="executePrint" :loading="printing" />
            </template>
        </Dialog>
    </div>
</template>
