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

async function fetchReport() {
    loading.value = true;
    try {
        data.value = await apiGet(`/api/reports/cash-flow?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`);
    } finally {
        loading.value = false;
    }
}

onMounted(fetchReport);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Laporan Arus Kas (Cash Flow)</h2>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-xl text-center">
                    <p class="text-sm text-muted-color m-0">Total Kas Masuk</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 m-0 mt-1">{{ formatRp(data.cash_in.total) }}</p>
                </div>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-xl text-center">
                    <p class="text-sm text-muted-color m-0">Total Kas Keluar</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 m-0 mt-1">{{ formatRp(data.cash_out.total) }}</p>
                </div>
                <div class="p-4 rounded-xl text-center"
                    :class="data.net_cash_flow >= 0
                        ? 'bg-blue-50 dark:bg-blue-900/20'
                        : 'bg-orange-100 dark:bg-orange-900/30'">
                    <p class="text-sm text-muted-color m-0">Arus Kas Bersih (Sisa Uang)</p>
                    <p class="text-3xl font-bold m-0 mt-1"
                        :class="data.net_cash_flow >= 0
                            ? 'text-blue-600 dark:text-blue-400'
                            : 'text-orange-600 dark:text-orange-400'">
                        {{ formatRp(data.net_cash_flow) }}
                    </p>
                </div>
            </div>

            <!-- Cash Flow Statement -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Detail Kas Masuk & Keluar -->
                <div class="flex flex-col gap-4">
                    <!-- KAS MASUK -->
                    <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                        <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold text-lg flex items-center text-green-600 dark:text-green-400">
                            <i class="pi pi-arrow-down mr-2"></i> Kas Masuk (Penerimaan)
                        </div>

                        <!-- Rincian Kas Masuk -->
                        <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Modal Awal Kasir</span>
                                <span class="font-semibold">{{ formatRp(data.cash_in.shift_capital) }}</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Penjualan Tunai</span>
                                <span class="font-semibold">{{ formatRp(data.cash_in.cash_sales) }}</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Penjualan Non-Tunai / QRIS</span>
                                <span class="font-semibold">{{ formatRp(data.cash_in.non_cash_sales) }}</span>
                            </div>
                        </div>

                        <div class="px-4 py-3 bg-surface-50 dark:bg-surface-900">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-green-600 dark:text-green-400">Total Kas Masuk</span>
                                <span class="font-bold text-green-600 dark:text-green-400 text-lg">{{ formatRp(data.cash_in.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- KAS KELUAR -->
                    <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                        <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold text-lg flex items-center text-red-600 dark:text-red-400">
                            <i class="pi pi-arrow-up mr-2"></i> Kas Keluar (Pengeluaran)
                        </div>

                        <!-- Rincian Kas Keluar -->
                        <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Belanja Stok (Pembelian)</span>
                                <span class="font-semibold text-red-500">- {{ formatRp(data.cash_out.purchases) }}</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Pengeluaran Operasional (Beban)</span>
                                <span class="font-semibold text-red-500">- {{ formatRp(data.cash_out.expenses) }}</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-muted-color">Refund / Retur Barang</span>
                                <span class="font-semibold text-red-500">- {{ formatRp(data.cash_out.refunds) }}</span>
                            </div>
                        </div>

                        <div class="px-4 py-3 bg-surface-50 dark:bg-surface-900">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-red-600 dark:text-red-400">Total Kas Keluar</span>
                                <span class="font-bold text-red-600 dark:text-red-400 text-lg">- {{ formatRp(data.cash_out.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Chart & Info -->
                <div class="flex flex-col gap-4">
                    <!-- Quick Info -->
                    <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-xl">
                        <p class="m-0 text-sm text-muted-color">
                            <i class="pi pi-info-circle mr-1 text-primary"></i>
                            <strong>Apa bedanya dengan Laba Rugi?</strong>
                        </p>
                        <p class="m-0 mt-2 text-sm text-muted-color leading-relaxed">
                            Laporan ini mencatat pergerakan uang tunai yang sesungguhnya. 
                            Bedanya, ketika Anda membeli stok barang, <strong>Laba Rugi</strong> hanya akan memotong laba Anda (sebagai HPP) pada saat barang tersebut <strong>terjual</strong>. 
                            Sebaliknya, <strong>Arus Kas</strong> langsung mencatat uang keluar saat itu juga, 
                            sehingga Anda dapat selalu mengetahui posisi uang sisa kas toko yang sebenarnya (uang di laci atau rekening).
                        </p>
                    </div>

                    <!-- Pergerakan Harian Table -->
                    <div class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden mt-2">
                        <div class="bg-surface-100 dark:bg-surface-800 px-4 py-3 font-bold">
                            Pergerakan Arus Kas Harian
                        </div>
                        <DataTable :value="data.daily_cash_flow" class="p-datatable-sm" emptyMessage="Belum ada transaksi di periode ini.">
                            <Column field="date" header="Tanggal" style="width: 8rem">
                                <template #body="{ data }">
                                    {{ new Date(data.date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'}) }}
                                </template>
                            </Column>
                            <Column header="Masuk">
                                <template #body="{ data }">
                                    <span v-if="data.cash_in > 0" class="text-green-600 dark:text-green-400 font-semibold">{{ formatRp(data.cash_in) }}</span>
                                    <span v-else class="text-muted-color">-</span>
                                </template>
                            </Column>
                            <Column header="Keluar">
                                <template #body="{ data }">
                                    <span v-if="data.cash_out > 0" class="text-red-500 font-semibold">- {{ formatRp(data.cash_out) }}</span>
                                    <span v-else class="text-muted-color">-</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
