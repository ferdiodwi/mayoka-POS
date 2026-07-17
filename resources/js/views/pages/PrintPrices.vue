<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const confirm = useConfirm();
const { hasPermission } = useAuth();

const printPrices = ref([]);
const loading = ref(false);
const submitting = ref(false);

// Price dialog
const priceDialogVisible = ref(false);
const priceDialogMode = ref('create');
const priceForm = ref({ paper_size: 'A4', color_type: 'bw', side_type: 'single', price_per_sheet: 0, cost_per_sheet: 0 });
const editingPriceId = ref(null);

// Import logic
const importDialogVisible = ref(false);
const uploading = ref(false);
const importFile = ref(null);
const fileInput = ref(null);

const paperSizes = [
    { label: 'A4', value: 'A4' }, 
    { label: 'F4', value: 'F4' }, 
    { label: 'A3', value: 'A3' },
    { label: 'Kertas Sendiri', value: 'Kertas Sendiri' }
];
const colorTypes = [{ label: 'Hitam Putih', value: 'bw' }, { label: 'Warna', value: 'color' }];
const sideTypes = [{ label: '1 Sisi', value: 'single' }, { label: 'Bolak-balik', value: 'duplex' }];

function colorLabel(v) { return v === 'bw' ? 'Hitam Putih' : 'Warna'; }
function sideLabel(v) { return v === 'single' ? '1 Sisi' : 'Bolak-balik'; }
function formatRp(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }

async function fetchData() {
    loading.value = true;
    try {
        const data = await apiGet('/api/print-prices');
        printPrices.value = data.print_prices;
    } finally {
        loading.value = false;
    }
}

// --- Price CRUD ---
function openCreatePrice() {
    priceDialogMode.value = 'create';
    priceForm.value = { paper_size: 'A4', color_type: 'bw', side_type: 'single', price_per_sheet: 0, cost_per_sheet: 0 };
    editingPriceId.value = null;
    priceDialogVisible.value = true;
}

function openEditPrice(item) {
    priceDialogMode.value = 'edit';
    priceForm.value = { price_per_sheet: parseFloat(item.price_per_sheet), cost_per_sheet: parseFloat(item.cost_per_sheet) };
    editingPriceId.value = item.id;
    priceDialogVisible.value = true;
}

async function savePrice() {
    submitting.value = true;
    try {
        const isEdit = priceDialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/print-prices/${editingPriceId.value}`, priceForm.value)
            : await apiPost('/api/print-prices', priceForm.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        priceDialogVisible.value = false;
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function deletePrice(item) {
    try {
        const data = await apiDelete(`/api/print-prices/${item.id}`);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

function confirmDeletePrice(item) {
    confirm.require({
        message: 'Apakah Anda yakin ingin menghapus harga cetak ini?',
        header: 'Konfirmasi Hapus',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-secondary p-button-text',
        acceptLabel: 'Ya, Hapus',
        rejectLabel: 'Batal',
        accept: () => deletePrice(item)
    });
}

// --- Import Logic ---
function openImport() {
    importFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
    importDialogVisible.value = true;
}

function handleFileChange(e) {
    importFile.value = e.target.files[0];
}

async function downloadTemplate() {
    window.location.href = '/api/print-prices/template';
}

async function submitImport() {
    if (!importFile.value) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Pilih file Excel terlebih dahulu.', life: 3000 });
        return;
    }

    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);

        const tokenMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = tokenMatch ? decodeURIComponent(tokenMatch[1]) : '';
        const branchId = localStorage.getItem('activeBranchId');

        const res = await fetch('/api/print-prices/import', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': token,
                ...(branchId ? { 'X-Branch-Id': branchId } : {}),
            }
        });
        
        const data = await res.json();
        
        if (!res.ok) throw new Error(data.message || 'Import gagal.');

        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Data harga cetak berhasil diimport.', life: 3000 });
        importDialogVisible.value = false;
        fetchData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        uploading.value = false;
    }
}

// Expanded rows removed

onMounted(fetchData);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Harga Cetak</h2>
            <div class="flex gap-2">
                <Button v-if="hasPermission('print_prices.create')" label="Import Excel" icon="pi pi-upload" severity="secondary" outlined @click="openImport" />
                <Button v-if="hasPermission('print_prices.create')" label="Tambah Kombinasi" icon="pi pi-plus" @click="openCreatePrice" />
            </div>
        </div>

        <DataTable :value="printPrices" :loading="loading"
            dataKey="id" stripedRows emptyMessage="Belum ada data harga cetak.">
            <Column field="paper_size" header="Ukuran" sortable style="width: 6rem" />
            <Column header="Tinta" sortable sortField="color_type" style="width: 8rem">
                <template #body="{ data }">
                    <Tag :value="colorLabel(data.color_type)"
                        :severity="data.color_type === 'bw' ? 'secondary' : 'warn'" />
                </template>
            </Column>
            <Column header="Sisi" sortable sortField="side_type" style="width: 8rem">
                <template #body="{ data }">{{ sideLabel(data.side_type) }}</template>
            </Column>
            <Column header="Harga/Lembar" sortable sortField="price_per_sheet">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatRp(data.price_per_sheet) }}</span>
                </template>
            </Column>
            <Column header="HPP/Lembar" sortable sortField="cost_per_sheet">
                <template #body="{ data }">{{ formatRp(data.cost_per_sheet) }}</template>
            </Column>
            <Column header="Aksi" style="width: 8rem" v-if="hasPermission('print_prices.update') || hasPermission('print_prices.delete')">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="hasPermission('print_prices.update')" icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEditPrice(data)" />
                        <Button v-if="hasPermission('print_prices.delete')" icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDeletePrice(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Price Dialog -->
        <Dialog v-model:visible="priceDialogVisible"
            :header="priceDialogMode === 'create' ? 'Tambah Harga Cetak' : 'Edit Harga Cetak'"
            modal :style="{ width: '480px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div v-if="priceDialogMode === 'create'" class="flex flex-col gap-2">
                    <label class="font-semibold">Ukuran Kertas</label>
                    <Select v-model="priceForm.paper_size" :options="paperSizes" optionLabel="label" optionValue="value" />
                </div>
                <div v-if="priceDialogMode === 'create'" class="flex flex-col gap-2">
                    <label class="font-semibold">Jenis Tinta</label>
                    <Select v-model="priceForm.color_type" :options="colorTypes" optionLabel="label" optionValue="value" />
                </div>
                <div v-if="priceDialogMode === 'create'" class="flex flex-col gap-2">
                    <label class="font-semibold">Sisi Cetak</label>
                    <Select v-model="priceForm.side_type" :options="sideTypes" optionLabel="label" optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Harga per Lembar (Rp)</label>
                    <InputNumber v-model="priceForm.price_per_sheet" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">HPP per Lembar (Rp)</label>
                    <InputNumber v-model="priceForm.cost_per_sheet" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="priceDialogVisible = false" />
                <Button :label="priceDialogMode === 'create' ? 'Simpan' : 'Perbarui'" icon="pi pi-check"
                    :loading="submitting" @click="savePrice" />
            </template>
        </Dialog>

        <!-- Import Excel Dialog -->
        <Dialog v-model:visible="importDialogVisible" header="Import Harga Cetak (Excel)" modal :style="{ width: '500px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="m-0 text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Instruksi Import:</p>
                    <ol class="m-0 pl-4 text-sm text-blue-600 dark:text-blue-400 space-y-1">
                        <li>Download template Excel.</li>
                        <li>Isi kombinasi Ukuran, Warna, Sisi, dan Harga.</li>
                        <li>Upload file yang sudah diisi ke sini.</li>
                    </ol>
                    <Button label="Download Template" icon="pi pi-download" class="mt-4 w-full" size="small" outlined @click="downloadTemplate" />
                </div>
                
                <div class="flex flex-col gap-2">
                    <label class="font-semibold text-sm">Upload File Excel (.xlsx)</label>
                    <input type="file" ref="fileInput" accept=".xlsx, .xls, .csv" @change="handleFileChange"
                        class="w-full border border-surface-300 dark:border-surface-600 rounded-lg p-2 text-sm
                               file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-semibold
                               file:bg-primary file:text-white hover:file:bg-primary-emphasis cursor-pointer" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" icon="pi pi-times" text @click="importDialogVisible = false" />
                <Button label="Mulai Import" icon="pi pi-check" @click="submitImport" :loading="uploading" :disabled="!importFile" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
