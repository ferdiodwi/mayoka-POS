<script setup>
import { ref, onMounted } from 'vue';
import { apiGet, apiPost, apiDelete } from '@/composables/useApi';
import ReturnDialog from '@/components/pos/ReturnDialog.vue';
import { useToast } from 'primevue/usetoast';
import { useQzTray } from '@/composables/useQzTray';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const { hasPermission } = useAuth();
const loading = ref(false);
const transactions = ref([]);
const receiptData = ref(null);
const activeTxId = ref(null);
const showReceipt = ref(false);
const { printReceipt: printViaQzTray } = useQzTray();
const currentPage = ref(1);
const totalRecords = ref(0);
const rowsPerPage = ref(20);
const searchQuery = ref('');

const showReturnDialog = ref(false);
const selectedTransaction = ref(null);

// Void state
const showVoidDialog = ref(false);
const voidTarget = ref(null);
const voidReason = ref('');
const voiding = ref(false);

function canVoid() {
    return hasPermission('transactions.delete');
}

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

const showVoided = ref(false);

async function fetchTransactions() {
    loading.value = true;
    try {
        let url = `/api/transactions?page=${currentPage.value}`;
        if (searchQuery.value.trim()) {
            url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
        }
        if (showVoided.value) {
            url += `&show_voided=1`;
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
        const res = await apiPost(`/api/transactions/${activeTxId.value}/print`);
        if (res.receipt_base64) {
            await printViaQzTray(res.receipt_base64);
        }
        showReceipt.value = false;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Cetak Gagal', detail: err.message || 'Gagal terhubung ke API cetak', life: 5000 });
    }
}

function openVoid(tx) {
    voidTarget.value = tx;
    voidReason.value = '';
    showVoidDialog.value = true;
}

async function confirmVoid() {
    if (!voidTarget.value) return;
    voiding.value = true;
    try {
        await apiDelete(`/api/transactions/${voidTarget.value.id}/void`, { void_reason: voidReason.value });
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Transaksi dibatalkan dan stok dikembalikan.', life: 4000 });
        showVoidDialog.value = false;
        fetchTransactions();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 5000 });
    } finally {
        voiding.value = false;
    }
}

async function restoreTransaction(tx) {
    try {
        await apiPost(`/api/transactions/${tx.id}/restore`);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Transaksi berhasil dipulihkan.', life: 4000 });
        fetchTransactions();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 5000 });
    }
}

async function forceDeleteTransaction(tx) {
    if (!confirm(`Hapus permanen transaksi ${tx.invoice_number}? Data tidak bisa dikembalikan!`)) return;
    try {
        await apiDelete(`/api/transactions/${tx.id}/force-delete`);
        toast.add({ severity: 'success', summary: 'Dihapus', detail: 'Transaksi dihapus permanen dari database.', life: 4000 });
        fetchTransactions();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 5000 });
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
                <Button v-if="canVoid()"
                    :label="showVoided ? 'Sembunyikan Void' : 'Tampilkan Void'"
                    :icon="showVoided ? 'pi pi-eye-slash' : 'pi pi-eye'"
                    :severity="showVoided ? 'danger' : 'secondary'"
                    outlined
                    @click="showVoided = !showVoided; fetchTransactions()" />
            </div>
        </div>

        <DataTable :value="transactions" :loading="loading" stripedRows lazy paginator
            :rows="rowsPerPage" :totalRecords="totalRecords" :first="(currentPage - 1) * rowsPerPage"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada riwayat transaksi."
            :rowClass="(data) => data.deleted_at ? 'opacity-60' : ''">
            <Column field="invoice_number" header="No. Invoice" sortable style="width: 11rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-semibold text-sm" :class="{ 'line-through text-muted-color': data.deleted_at }">
                            {{ data.invoice_number }}
                        </span>
                        <Tag v-if="data.deleted_at" value="VOID" severity="danger" class="text-xs" />
                    </div>
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
            <Column header="Aksi" style="width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <!-- Normal transaction actions -->
                        <template v-if="!data.deleted_at">
                            <Button icon="pi pi-print" size="small" severity="secondary" outlined
                                v-tooltip.top="'Cetak Ulang Struk'" @click="reprintReceipt(data.id)" />
                            <Button v-if="hasReturnableItems(data)" icon="pi pi-receipt" size="small" severity="warn" outlined
                                v-tooltip.top="'Retur Barang'" @click="openReturn(data)" />
                            <Button v-if="canVoid()" icon="pi pi-ban" size="small" severity="danger" outlined
                                v-tooltip.top="'Batalkan Transaksi'" @click="openVoid(data)" />
                        </template>
                        <!-- Voided transaction: restore + hard delete (owner only) -->
                        <template v-else-if="canVoid()">
                            <Button icon="pi pi-replay" size="small" severity="success" outlined
                                v-tooltip.top="'Pulihkan Transaksi'" @click="restoreTransaction(data)" />
                            <Button icon="pi pi-trash" size="small" severity="danger"
                                v-tooltip.top="'Hapus Permanen'" @click="forceDeleteTransaction(data)" />
                        </template>
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <!-- Return & Receipt Dialogs -->
    <ReturnDialog v-model:visible="showReturnDialog" :transaction="selectedTransaction" @success="fetchTransactions" />

    <!-- Void Confirmation Dialog -->
    <Dialog v-model:visible="showVoidDialog" header="Batalkan Transaksi" modal :style="{ width: '420px' }">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                <i class="pi pi-exclamation-triangle text-red-500 text-2xl"></i>
                <div>
                    <p class="font-semibold m-0 text-red-700 dark:text-red-400">Yakin ingin membatalkan transaksi ini?</p>
                    <p class="text-sm text-red-600 dark:text-red-300 m-0 mt-1">
                        Invoice: <strong>{{ voidTarget?.invoice_number }}</strong> — Total: <strong>{{ formatRp(voidTarget?.total ?? 0) }}</strong>
                    </p>
                    <p class="text-xs text-muted-color m-0 mt-1">Stok barang akan dikembalikan secara otomatis.</p>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold">Alasan Pembatalan <span class="text-muted-color font-normal">(opsional)</span></label>
                <InputText v-model="voidReason" placeholder="Contoh: Salah input, double transaksi..." />
            </div>
        </div>
        <template #footer>
            <Button label="Batal" severity="secondary" text @click="showVoidDialog = false" />
            <Button label="Ya, Batalkan Transaksi" icon="pi pi-ban" severity="danger" :loading="voiding" @click="confirmVoid" />
        </template>
    </Dialog>

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
