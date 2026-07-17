<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const confirm = useConfirm();
const { hasPermission } = useAuth();

const addons = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create');
const form = ref({ name: '', price: 0 });
const editingId = ref(null);
const submitting = ref(false);

// Import logic
const importDialogVisible = ref(false);
const uploading = ref(false);
const importFile = ref(null);
const fileInput = ref(null);

function formatRp(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }

async function fetchData() {
    loading.value = true;
    try {
        const data = await apiGet('/api/addon-services');
        addons.value = data.addon_services;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    dialogMode.value = 'create';
    form.value = { name: '', price: 0 };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    form.value = { name: item.name, price: parseFloat(item.price), is_active: item.is_active };
    editingId.value = item.id;
    dialogVisible.value = true;
}

async function save() {
    submitting.value = true;
    try {
        const isEdit = dialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/addon-services/${editingId.value}`, form.value)
            : await apiPost('/api/addon-services', form.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        dialogVisible.value = false;
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function deleteAddon(item) {
    try {
        const data = await apiDelete(`/api/addon-services/${item.id}`);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

function confirmDelete(item) {
    confirm.require({
        message: `Apakah Anda yakin ingin menghapus jasa "${item.name}"?`,
        header: 'Konfirmasi Hapus',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-secondary p-button-text',
        acceptLabel: 'Ya, Hapus',
        rejectLabel: 'Batal',
        accept: () => deleteAddon(item)
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
    window.location.href = '/api/addon-services/template';
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

        const res = await fetch('/api/addon-services/import', {
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

        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Data jasa tambahan berhasil diimport.', life: 3000 });
        importDialogVisible.value = false;
        fetchData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        uploading.value = false;
    }
}

onMounted(fetchData);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Jasa Tambahan (Addon)</h2>
            <div class="flex gap-2">
                <Button v-if="hasPermission('addons.create')" label="Import Excel" icon="pi pi-upload" severity="secondary" outlined @click="openImport" />
                <Button v-if="hasPermission('addons.create')" label="Tambah Jasa" icon="pi pi-plus" @click="openCreate" />
            </div>
        </div>

        <DataTable :value="addons" :loading="loading" stripedRows dataKey="id"
            emptyMessage="Belum ada jasa tambahan.">
            <Column header="No" style="width: 4rem">
                <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column field="name" header="Nama Jasa" sortable />
            <Column header="Harga" sortable sortField="price">
                <template #body="{ data }">{{ formatRp(data.price) }}</template>
            </Column>
            <Column header="Status" style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 10rem" v-if="hasPermission('addons.update') || hasPermission('addons.delete')">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button v-if="hasPermission('addons.update')" icon="pi pi-pencil" severity="info" text rounded @click="openEdit(data)" />
                        <Button v-if="hasPermission('addons.delete')" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Jasa Tambahan' : 'Edit Jasa Tambahan'"
            modal :style="{ width: '420px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Nama Jasa</label>
                    <InputText v-model="form.name" placeholder="Misal: Jilid Lakban" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Harga (Rp)</label>
                    <InputNumber v-model="form.price" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div v-if="dialogMode === 'edit'" class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" />
                    <label>{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</label>
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button :label="dialogMode === 'create' ? 'Simpan' : 'Perbarui'" icon="pi pi-check"
                    :loading="submitting" @click="save" />
            </template>
        </Dialog>

        <!-- Import Excel Dialog -->
        <Dialog v-model:visible="importDialogVisible" header="Import Jasa Tambahan (Excel)" modal :style="{ width: '500px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="m-0 text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Instruksi Import:</p>
                    <ol class="m-0 pl-4 text-sm text-blue-600 dark:text-blue-400 space-y-1">
                        <li>Download template Excel.</li>
                        <li>Isi Nama Jasa, Harga, dan Status Aktif (Ya/Tidak).</li>
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
