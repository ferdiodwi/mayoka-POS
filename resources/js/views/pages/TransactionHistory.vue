<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';
import ReturnDialog from '@/components/pos/ReturnDialog.vue';

const loading = ref(false);
const transactions = ref([]);
const receiptData = ref(null);
const showReceipt = ref(false);
const currentPage = ref(1);
const totalRecords = ref(0);
const rowsPerPage = ref(20);

const showReturnDialog = ref(false);
const selectedTransaction = ref(null);

function hasReturnableItems(tx) {
    if (!tx || !tx.items) return false;
    return tx.items.some(i => i.item_type === 'product' && (i.qty - (i.returned_qty || 0)) > 0);
}

function openReturn(tx) {
    selectedTransaction.value = tx;
    showReturnDialog.value = true;
}

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatTime(dt) {
    return new Date(dt).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function fetchTransactions() {
    loading.value = true;
    try {
        const data = await apiGet(`/api/transactions?page=${currentPage.value}`);
        transactions.value = data.data || [];
        totalRecords.value = data.total;
        currentPage.value = data.current_page;
    } finally {
        loading.value = false;
    }
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchTransactions();
}

async function reprintReceipt(txId) {
    try {
        const data = await apiGet(`/api/transactions/${txId}/receipt`);
        receiptData.value = data.receipt;
        showReceipt.value = true;
    } catch (err) {
        console.error('Gagal memuat struk:', err);
    }
}

function printReceipt() {
    const printContent = document.getElementById('receipt-print-area');
    if (!printContent) return;

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(`
        <html>
        <head>
            <title>Struk - ${receiptData.value.invoice_number}</title>
            <style>
                body { font-family: 'Courier New', monospace; font-size: 12px; padding: 10px; max-width: 300px; margin: 0 auto; }
                .center { text-align: center; }
                .bold { font-weight: bold; }
                .divider { border-top: 1px dashed #333; margin: 8px 0; }
                .row { display: flex; justify-content: space-between; }
                .small { font-size: 10px; }
                .addon { padding-left: 12px; font-size: 11px; color: #666; }
                .total-row { font-size: 14px; font-weight: bold; }
            </style>
        </head>
        <body>
            ${printContent.innerHTML}
            <script>window.onload = function() { window.print(); window.close(); }<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

onMounted(fetchTransactions);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">
                <i class="pi pi-history mr-2"></i>Riwayat Transaksi
            </h2>
            <Button label="Refresh" icon="pi pi-refresh" size="small" outlined @click="fetchTransactions" />
        </div>

        <DataTable :value="transactions" :loading="loading" stripedRows lazy paginator
            :rows="rowsPerPage" :totalRecords="totalRecords" :first="(currentPage - 1) * rowsPerPage"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada riwayat transaksi.">
            <Column field="invoice_number" header="No. Invoice" sortable style="width: 11rem">
                <template #body="{ data }">
                    <span class="font-mono font-semibold text-sm">{{ data.invoice_number }}</span>
                </template>
            </Column>
            <Column header="Waktu" sortable sortField="created_at" style="width: 10rem">
                <template #body="{ data }">{{ formatTime(data.created_at) }}</template>
            </Column>
            <Column header="Kasir" style="width: 7rem">
                <template #body="{ data }">{{ data.user?.name || '-' }}</template>
            </Column>
            <Column header="Total" sortable sortField="total">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatRp(data.total) }}</span>
                </template>
            </Column>
            <Column header="Metode" style="width: 5rem">
                <template #body="{ data }">
                    <Tag :value="data.payment_method.toUpperCase()"
                        :severity="data.payment_method === 'cash' ? 'success' : data.payment_method === 'qris' ? 'info' : 'warn'" />
                </template>
            </Column>
            <Column header="Bayar" style="width: 7rem">
                <template #body="{ data }">{{ formatRp(data.cash_paid) }}</template>
            </Column>
            <Column header="Kembali" style="width: 7rem">
                <template #body="{ data }">
                    <span v-if="data.payment_method === 'cash'">{{ formatRp(data.cash_change) }}</span>
                    <span v-else class="text-muted-color">—</span>
                </template>
            </Column>
            <Column header="Aksi" style="width: 8rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-print" size="small" severity="secondary" outlined
                            v-tooltip.top="'Cetak Ulang Struk'" @click="reprintReceipt(data.id)" />
                        <Button v-if="hasReturnableItems(data)" icon="pi pi-receipt" size="small" severity="danger" outlined
                            v-tooltip.top="'Retur Barang'" @click="openReturn(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <!-- Receipt Dialog -->
    <ReturnDialog v-model:visible="showReturnDialog" :transaction="selectedTransaction" @success="fetchTransactions" />
    <Dialog :visible="showReceipt" header="Cetak Ulang Struk" modal :style="{ width: '420px' }"
        @update:visible="showReceipt = $event">
        <div v-if="receiptData" id="receipt-print-area" class="font-mono text-sm">
            <div class="text-center mb-3">
                <p class="font-bold text-lg m-0">{{ receiptData.store_name }}</p>
                <p class="text-xs text-muted-color m-0">{{ receiptData.store_address }}</p>
                <p class="text-xs m-0 mt-1">{{ receiptData.date }}</p>
                <p class="text-xs m-0">No: {{ receiptData.invoice_number }}</p>
                <p class="text-xs m-0">Kasir: {{ receiptData.cashier }}</p>
            </div>
            <hr class="border-dashed" />

            <div v-for="(item, i) in receiptData.items" :key="i" class="py-1">
                <div class="flex justify-between">
                    <span>{{ item.description }}</span>
                </div>
                <div class="flex justify-between text-muted-color">
                    <span>{{ item.qty }} x {{ formatRp(item.unit_price) }}</span>
                    <span>{{ formatRp(item.subtotal) }}</span>
                </div>
                <div v-for="(addon, ai) in item.addons" :key="ai"
                    class="flex justify-between text-xs text-muted-color ml-2">
                    <span>+ {{ addon.description }}</span>
                    <span>{{ formatRp(addon.price) }}</span>
                </div>
            </div>

            <hr class="border-dashed" />

            <div class="flex justify-between py-1">
                <span>Subtotal</span>
                <span>{{ formatRp(receiptData.subtotal) }}</span>
            </div>
            <div v-if="receiptData.discount > 0" class="flex justify-between py-1 text-red-500">
                <span>Diskon</span>
                <span>-{{ formatRp(receiptData.discount) }}</span>
            </div>
            <div class="flex justify-between py-1 font-bold text-lg">
                <span>TOTAL</span>
                <span>{{ formatRp(receiptData.total) }}</span>
            </div>

            <hr class="border-dashed" />

            <div class="flex justify-between py-1">
                <span>Bayar ({{ receiptData.payment_method.toUpperCase() }})</span>
                <span>{{ formatRp(receiptData.cash_paid) }}</span>
            </div>
            <div v-if="receiptData.payment_method === 'cash'" class="flex justify-between py-1">
                <span>Kembali</span>
                <span>{{ formatRp(receiptData.cash_change) }}</span>
            </div>

            <hr class="border-dashed" />
            <p class="text-center text-xs text-muted-color mt-2 m-0">Terima kasih atas kunjungan Anda!</p>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="showReceipt = false" />
            <Button label="Cetak" icon="pi pi-print" @click="printReceipt" />
        </template>
    </Dialog>
</template>
