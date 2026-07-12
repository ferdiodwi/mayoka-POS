<script setup>
import { ref, onMounted } from 'vue';
import { apiGet, apiPost } from '@/composables/useApi';
import ReturnDialog from '@/components/pos/ReturnDialog.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const loading = ref(false);
const transactions = ref([]);
const receiptData = ref(null);
const activeTxId = ref(null);
const showReceipt = ref(false);
const currentPage = ref(1);
const totalRecords = ref(0);
const rowsPerPage = ref(20);
const searchQuery = ref('');

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
        let url = `/api/transactions?page=${currentPage.value}`;
        if (searchQuery.value.trim()) {
            url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
        }
        const data = await apiGet(url);
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
        activeTxId.value = txId;
        showReceipt.value = true;
    } catch (err) {
        console.error('Gagal memuat struk:', err);
    }
}

async function printReceipt() {
    if (!activeTxId.value) return;
    
    try {
        await apiPost(`/api/transactions/${activeTxId.value}/print`);
        toast.add({ severity: 'success', summary: 'Cetak Berhasil', detail: 'Perintah cetak terkirim ke printer.', life: 3000 });
        showReceipt.value = false;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Cetak Gagal', detail: err.message || 'Gagal terhubung ke printer', life: 5000 });
    }
}

onMounted(fetchTransactions);
</script>

<template>
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-2xl font-semibold m-0">
                <i class="pi pi-history mr-2"></i>Riwayat Transaksi
            </h2>
            <div class="flex items-center gap-2">
                <IconField iconPosition="left">
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="searchQuery" placeholder="Cari No. Struk (INV-...)" class="w-full sm:w-64" @keyup.enter="fetchTransactions" />
                </IconField>
                <Button label="Cari" icon="pi pi-search" @click="fetchTransactions" />
                <Button label="Refresh" icon="pi pi-refresh" outlined @click="fetchTransactions" />
            </div>
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
            <p class="text-center text-xs mt-2 m-0">Terima Kasih Atas Kunjungan Anda</p>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="showReceipt = false" />
            <Button label="Cetak" icon="pi pi-print" @click="printReceipt" />
        </template>
    </Dialog>
</template>

<style scoped>
.print-spacer {
    display: none;
}
</style>
