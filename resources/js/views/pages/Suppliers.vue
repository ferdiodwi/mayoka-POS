<script setup>
import { ref, onMounted } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { apiFetch, apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const { hasPermission } = useAuth();
const suppliers = ref([]);
const loading = ref(true);
const supplierDialog = ref(false);
const deleteDialog = ref(false);
const importDialog = ref(false);
const supplier = ref({});
const submitted = ref(false);
const isEdit = ref(false);
const importFile = ref(null);
const uploading = ref(false);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

async function loadSuppliers() {
    loading.value = true;
    try {
        const res = await apiGet('/api/suppliers?per_page=all');
        suppliers.value = res.suppliers || [];
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data supplier', life: 3000 });
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSuppliers();
});

function openNew() {
    supplier.value = { is_active: true };
    submitted.value = false;
    isEdit.value = false;
    supplierDialog.value = true;
}

function editSupplier(data) {
    supplier.value = { ...data };
    submitted.value = false;
    isEdit.value = true;
    supplierDialog.value = true;
}

function confirmDelete(data) {
    supplier.value = data;
    deleteDialog.value = true;
}

function hideDialog() {
    supplierDialog.value = false;
    submitted.value = false;
}

async function saveSupplier() {
    submitted.value = true;
    if (!supplier.value.name) return;

    try {
        if (isEdit.value) {
            await apiPut(`/api/suppliers/${supplier.value.id}`, supplier.value);
            toast.add({ severity: 'success', summary: 'Sukses', detail: 'Supplier berhasil diperbarui', life: 3000 });
        } else {
            await apiPost('/api/suppliers', supplier.value);
            toast.add({ severity: 'success', summary: 'Sukses', detail: 'Supplier berhasil ditambahkan', life: 3000 });
        }
        hideDialog();
        loadSuppliers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.message || 'Gagal menyimpan data', life: 5000 });
    }
}

async function deleteSupplier() {
    try {
        await apiDelete(`/api/suppliers/${supplier.value.id}`);
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Supplier dihapus/dinonaktifkan', life: 3000 });
        deleteDialog.value = false;
        supplier.value = {};
        loadSuppliers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.message || 'Gagal menghapus data', life: 5000 });
        deleteDialog.value = false;
    }
}

function onFileSelect(event) {
    importFile.value = event.files[0];
}

async function importData() {
    if (!importFile.value) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Pilih file terlebih dahulu', life: 3000 });
        return;
    }

    uploading.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value);

    try {
        await apiFetch('/api/suppliers/import', {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set multipart/form-data with boundary
        });
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Data diimport', life: 3000 });
        importDialog.value = false;
        loadSuppliers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 5000 });
    } finally {
        uploading.value = false;
    }
}

function downloadTemplate() {
    window.location.href = '/api/suppliers/template';
}
</script>

<template>
    <div class="card">
        <Toast />
        <Toolbar class="mb-4">
            <template #start>
                <Button v-if="hasPermission('purchases.create')" label="Tambah" icon="pi pi-plus" severity="success" class="mr-2" @click="openNew" />
                <Button v-if="hasPermission('purchases.create')" label="Import Excel" icon="pi pi-upload" severity="secondary" @click="importDialog = true" />
            </template>
        </Toolbar>

        <DataTable ref="dt" :value="suppliers" :loading="loading" dataKey="id"
            :paginator="true" :rows="10" :filters="filters"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            :rowsPerPageOptions="[10, 25, 50]"
            currentPageReportTemplate="Menampilkan {first} sampai {last} dari {totalRecords} supplier">
            <template #header>
                <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                    <h5 class="m-0">Master Data Supplier</h5>
                    <IconField iconPosition="left" class="block mt-2 md:mt-0">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filters['global'].value" placeholder="Cari..." />
                    </IconField>
                </div>
            </template>

            <Column field="code" header="Kode" :sortable="true" style="width: 15%"></Column>
            <Column field="name" header="Nama Supplier" :sortable="true" style="width: 25%"></Column>
            <Column field="phone" header="Telepon" style="width: 15%"></Column>
            <Column field="address" header="Alamat" style="width: 25%"></Column>
            <Column field="is_active" header="Status" :sortable="true" style="width: 10%">
                <template #body="slotProps">
                    <Tag :value="slotProps.data.is_active ? 'Aktif' : 'Nonaktif'" :severity="slotProps.data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 10%" v-if="hasPermission('purchases.update') || hasPermission('purchases.delete')">
                <template #body="slotProps">
                    <Button v-if="hasPermission('purchases.update')" icon="pi pi-pencil" outlined rounded class="mr-2" @click="editSupplier(slotProps.data)" />
                    <Button v-if="hasPermission('purchases.delete')" icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDelete(slotProps.data)" />
                </template>
            </Column>
        </DataTable>

        <!-- Dialog Create/Edit -->
        <Dialog v-model:visible="supplierDialog" :style="{ width: '450px' }" header="Detail Supplier" :modal="true" class="p-fluid">
            <div class="flex flex-col gap-2 mb-4 mt-2">
                <label for="name" class="font-semibold">Nama Supplier</label>
                <InputText id="name" v-model.trim="supplier.name" required="true" autofocus :invalid="submitted && !supplier.name" />
                <small class="p-error" v-if="submitted && !supplier.name">Nama harus diisi.</small>
            </div>

            <div class="flex flex-col gap-2 mb-4">
                <label for="phone" class="font-semibold">Telepon</label>
                <InputText id="phone" v-model.trim="supplier.phone" />
            </div>

            <div class="flex flex-col gap-2 mb-4">
                <label for="address" class="font-semibold">Alamat</label>
                <Textarea id="address" v-model="supplier.address" rows="3" cols="20" />
            </div>

            <div class="flex flex-col gap-2 mb-4">
                <label for="notes" class="font-semibold">Catatan</label>
                <InputText id="notes" v-model.trim="supplier.notes" />
            </div>

            <div class="flex items-center gap-2 mb-4">
                <ToggleSwitch inputId="is_active" v-model="supplier.is_active" />
                <label for="is_active">Aktif</label>
            </div>

            <template #footer>
                <Button label="Batal" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Simpan" icon="pi pi-check" text @click="saveSupplier" />
            </template>
        </Dialog>

        <!-- Dialog Delete -->
        <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Konfirmasi" :modal="true">
            <div class="flex items-center justify-center">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span v-if="supplier">Apakah Anda yakin ingin menghapus supplier <b>{{ supplier.name }}</b>?</span>
            </div>
            <template #footer>
                <Button label="Tidak" icon="pi pi-times" text @click="deleteDialog = false" />
                <Button label="Ya" icon="pi pi-check" text @click="deleteSupplier" />
            </template>
        </Dialog>

        <!-- Import Dialog -->
        <Dialog v-model:visible="importDialog" :style="{ width: '450px' }" header="Import Supplier" :modal="true">
            <div class="flex flex-col gap-4">
                <p>Silakan download template Excel, isi data, dan upload kembali ke sistem.</p>
                <Button label="Download Template" icon="pi pi-download" severity="info" outlined @click="downloadTemplate" />
                
                <div class="mt-4">
                    <label class="font-semibold block mb-2">Upload File Excel</label>
                    <FileUpload mode="basic" name="file" accept=".xlsx,.xls,.csv" :maxFileSize="2000000" @select="onFileSelect" :auto="false" chooseLabel="Pilih File" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" icon="pi pi-times" text @click="importDialog = false" />
                <Button label="Import" icon="pi pi-check" @click="importData" :loading="uploading" />
            </template>
        </Dialog>
    </div>
</template>
