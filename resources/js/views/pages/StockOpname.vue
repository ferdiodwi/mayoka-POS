<script setup>
import { ref, onMounted, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const confirm = useConfirm();
const { hasPermission } = useAuth();

const opnames = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const detailDialogVisible = ref(false);
const submitting = ref(false);
const selectedOpname = ref(null);

const currentPage = ref(1);
const totalRecords = ref(0);
const filterStatus = ref(null);

const products = ref([]);
const form = ref({
    id: null,
    opname_date: new Date(),
    notes: '',
    items: [],
});

const formFilters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

function formatDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

async function fetchOpnames() {
    loading.value = true;
    try {
        let url = `/api/stock-opname?page=${currentPage.value}`;
        if (filterStatus.value) url += `&status=${filterStatus.value}`;
        
        const data = await apiGet(url);
        opnames.value = data.data;
        totalRecords.value = data.total;
    } finally {
        loading.value = false;
    }
}

function applyFilter() {
    currentPage.value = 1;
    fetchOpnames();
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchOpnames();
}

async function fetchProducts() {
    const data = await apiGet('/api/products?per_page=all');
    products.value = (Array.isArray(data) ? data : data.data || []).filter(p => p.type === 'barang');
}

async function openCreate() {
    await fetchProducts();
    form.value = {
        id: null,
        opname_date: new Date(),
        notes: '',
        items: products.value.map(p => ({
            product_id: p.id,
            product_name: p.name,
            system_stock: p.stock,
            physical_stock: p.stock,
            difference: 0,
            notes: '',
        })),
    };
    dialogVisible.value = true;
}

async function editDraft(opname) {
    try {
        const data = await apiGet(`/api/stock-opname/${opname.id}`);
        const opData = data.stock_opname;
        form.value = {
            id: opData.id,
            opname_date: new Date(opData.opname_date),
            notes: opData.notes || '',
            items: opData.items.map(i => ({
                id: i.id,
                product_id: i.product_id,
                product_name: i.product.name,
                system_stock: i.system_stock,
                physical_stock: i.physical_stock,
                difference: i.difference,
                notes: i.notes || '',
            })),
        };
        dialogVisible.value = true;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 3000 });
    }
}

function calcDiff(item) {
    item.difference = item.physical_stock - item.system_stock;
}

async function saveDraft() {
    submitting.value = true;
    try {
        if (form.value.id) {
            await apiPut(`/api/stock-opname/${form.value.id}`, {
                notes: form.value.notes,
                items: form.value.items,
            });
        } else {
            await apiPost('/api/stock-opname', {
                opname_date: toApiDate(form.value.opname_date),
                notes: form.value.notes,
                items: form.value.items,
            });
        }
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Draft disimpan.', life: 3000 });
        dialogVisible.value = false;
        fetchOpnames();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function confirmComplete(opname = null) {
    // If called from inside dialog, we save draft first, then complete.
    // If called from list, we just complete.
    
    confirm.require({
        message: 'Finalisasi Stok Opname? Stok produk akan diubah sesuai dengan stok fisik dan tidak dapat diedit lagi.',
        header: 'Konfirmasi Finalisasi',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                let opnameId = opname ? opname.id : form.value.id;
                
                // If creating new and finalizing immediately
                if (!opnameId) {
                    const res = await apiPost('/api/stock-opname', {
                        opname_date: toApiDate(form.value.opname_date),
                        notes: form.value.notes,
                        items: form.value.items,
                    });
                    opnameId = res.stock_opname.id;
                } else if (!opname) {
                    // Updating draft before final
                    await apiPut(`/api/stock-opname/${opnameId}`, {
                        notes: form.value.notes,
                        items: form.value.items,
                    });
                }
                
                await apiPost(`/api/stock-opname/${opnameId}/complete`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Stok Opname difinalisasi.', life: 3000 });
                dialogVisible.value = false;
                fetchOpnames();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        }
    });
}

async function viewDetail(opname) {
    try {
        const data = await apiGet(`/api/stock-opname/${opname.id}`);
        selectedOpname.value = data.stock_opname;
        detailDialogVisible.value = true;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 3000 });
    }
}

function exportCsv(id) {
    window.location.href = `/api/stock-opname/export?id=${id}`;
}

onMounted(() => {
    fetchOpnames();
});
</script>

<template>
    <div class="card">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
            <h2 class="text-2xl font-semibold m-0">Stok Opname</h2>
            <Button v-if="hasPermission('purchases.create')" label="Buat Opname Baru" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4 mb-6">
            <div class="flex flex-col gap-1 w-full sm:w-auto">
                <label class="text-sm font-semibold text-muted-color">Status</label>
                <Select v-model="filterStatus" :options="[{label:'Semua',value:null},{label:'Draft',value:'draft'},{label:'Selesai',value:'completed'}]"
                    optionLabel="label" optionValue="value" @change="applyFilter" />
            </div>
            <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="applyFilter" />
        </div>

        <DataTable :value="opnames" :loading="loading" stripedRows lazy paginator
            :rows="20" :totalRecords="totalRecords" :first="(currentPage - 1) * 20"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada data.">
            <Column field="opname_number" header="No. Opname" sortable style="width: 12rem" />
            <Column header="Tanggal" style="width: 10rem">
                <template #body="{ data }">{{ formatDate(data.opname_date) }}</template>
            </Column>
            <Column header="Status" style="width: 8rem">
                <template #body="{ data }">
                    <Tag :value="data.status === 'completed' ? 'Selesai' : 'Draft'"
                        :severity="data.status === 'completed' ? 'success' : 'warn'" />
                </template>
            </Column>
            <Column header="Dicatat Oleh">
                <template #body="{ data }">{{ data.user?.name }}</template>
            </Column>
            <Column header="Aksi" style="width: 12rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded size="small" @click="viewDetail(data)" v-tooltip="'Detail'" />
                        <Button v-if="data.status === 'draft' && hasPermission('purchases.update')" icon="pi pi-pencil" severity="warn" text rounded size="small" @click="editDraft(data)" v-tooltip="'Lanjutkan Draft'" />
                        <Button v-if="data.status === 'draft' && hasPermission('purchases.update')" icon="pi pi-check-circle" severity="success" text rounded size="small" @click="confirmComplete(data)" v-tooltip="'Finalisasi'" />
                        <Button v-if="data.status === 'completed'" icon="pi pi-file-excel" severity="success" text rounded size="small" @click="exportCsv(data.id)" v-tooltip="'Export CSV'" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Form Dialog -->
        <Dialog v-model:visible="dialogVisible" header="Stok Opname" modal maximize
            :style="{ width: '90vw' }" :breakpoints="{ '768px': '95vw' }">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-surface-50 dark:bg-surface-900 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Tanggal Opname</label>
                        <DatePicker v-model="form.opname_date" dateFormat="dd/mm/yy" showIcon :disabled="form.id != null" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Catatan</label>
                        <InputText v-model="form.notes" placeholder="Opsional" />
                    </div>
                </div>

                <div class="flex justify-between items-center mb-2">
                    <h3 class="m-0 text-lg font-semibold">Daftar Produk</h3>
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="formFilters['global'].value" placeholder="Cari produk..." />
                    </IconField>
                </div>

                <DataTable :value="form.items" dataKey="product_id" class="p-datatable-sm border rounded-lg"
                    :filters="formFilters" :paginator="true" :rows="20"
                    emptyMessage="Tidak ada produk.">
                    <Column field="product_name" header="Produk" />
                    <Column header="Stok Sistem" style="width: 8rem">
                        <template #body="{ data }">
                            <span class="font-semibold text-lg">{{ data.system_stock }}</span>
                        </template>
                    </Column>
                    <Column header="Stok Fisik" style="width: 10rem">
                        <template #body="{ data }">
                            <InputNumber v-model="data.physical_stock" :min="0" showButtons size="small"
                                class="w-24" @update:modelValue="calcDiff(data)" />
                        </template>
                    </Column>
                    <Column header="Selisih" style="width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="data.difference > 0 ? `+${data.difference}` : data.difference"
                                :severity="data.difference > 0 ? 'success' : data.difference < 0 ? 'danger' : 'secondary'"
                                class="text-sm px-3 py-1" />
                        </template>
                    </Column>
                    <Column header="Catatan" style="width: 12rem">
                        <template #body="{ data }">
                            <InputText v-model="data.notes" size="small" placeholder="..." />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <template #footer>
                <div class="flex justify-between w-full">
                    <Button label="Batal" severity="secondary" text @click="dialogVisible = false" />
                    <div class="flex gap-2">
                        <Button label="Simpan Draft" icon="pi pi-save" severity="info" outlined :loading="submitting" @click="saveDraft" />
                        <Button label="Finalisasi & Update Stok" icon="pi pi-check-circle" severity="success" @click="confirmComplete(null)" />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- Detail Dialog -->
        <Dialog v-model:visible="detailDialogVisible" header="Detail Stok Opname" modal
            :style="{ width: '800px' }" :breakpoints="{ '768px': '95vw' }">
            <div v-if="selectedOpname" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4 p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <div><span class="text-sm text-muted-color">No. Opname</span>
                        <p class="m-0 font-semibold">{{ selectedOpname.opname_number }}</p></div>
                    <div><span class="text-sm text-muted-color">Tanggal</span>
                        <p class="m-0 font-semibold">{{ formatDate(selectedOpname.opname_date) }}</p></div>
                    <div><span class="text-sm text-muted-color">Dicatat Oleh</span>
                        <p class="m-0 font-semibold">{{ selectedOpname.user?.name }}</p></div>
                    <div><span class="text-sm text-muted-color">Status</span>
                        <p class="m-0"><Tag :value="selectedOpname.status === 'completed' ? 'Selesai' : 'Draft'"
                            :severity="selectedOpname.status === 'completed' ? 'success' : 'warn'" /></p></div>
                </div>

                <DataTable :value="selectedOpname.items" dataKey="id" class="p-datatable-sm"
                    :paginator="true" :rows="10">
                    <Column field="product.name" header="Produk" />
                    <Column header="Sistem" style="width: 6rem">
                        <template #body="{ data }">{{ data.system_stock }}</template>
                    </Column>
                    <Column header="Fisik" style="width: 6rem">
                        <template #body="{ data }">{{ data.physical_stock }}</template>
                    </Column>
                    <Column header="Selisih" style="width: 6rem">
                        <template #body="{ data }">
                            <Tag :value="data.difference > 0 ? `+${data.difference}` : data.difference"
                                :severity="data.difference > 0 ? 'success' : data.difference < 0 ? 'danger' : 'secondary'" />
                        </template>
                    </Column>
                    <Column field="notes" header="Catatan" />
                </DataTable>

                <p v-if="selectedOpname.notes" class="m-0 text-sm text-muted-color">
                    <i class="pi pi-info-circle mr-1"></i> {{ selectedOpname.notes }}
                </p>
                
                <div class="flex justify-end mt-4">
                    <Button label="Export CSV" icon="pi pi-file-excel" severity="success" @click="exportCsv(selectedOpname.id)" />
                </div>
            </div>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
