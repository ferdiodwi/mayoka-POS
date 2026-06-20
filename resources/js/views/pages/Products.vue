<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();
const confirm = useConfirm();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const adjustDialogVisible = ref(false);
const dialogMode = ref('create');
const submitting = ref(false);
const editingId = ref(null);

// Filters
const filterCategory = ref(null);
const filterType = ref(null);
const filterLowStock = ref(false);
const searchQuery = ref('');

const typeOptions = [
    { label: 'Semua', value: null },
    { label: 'Barang', value: 'barang' },
    { label: 'Jasa', value: 'jasa' },
];

const form = ref({
    category_id: null, name: '', barcode: '', type: 'barang',
    price: 0, cost_price: 0, stock: 0, min_stock: 0, unit: 'pcs', is_active: true,
});

const adjustForm = ref({ qty: 0, notes: '' });
const adjustProduct = ref(null);

const currentPage = ref(1);
const totalRecords = ref(0);
const rowsPerPage = ref(20);

async function fetchProducts() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('page', currentPage.value);
        if (filterCategory.value) params.append('category_id', filterCategory.value);
        if (filterType.value) params.append('type', filterType.value);
        if (filterLowStock.value) params.append('low_stock', '1');
        if (searchQuery.value) params.append('search', searchQuery.value);

        const data = await apiGet(`/api/products?${params}`);
        products.value = data.data;
        totalRecords.value = data.total;
        currentPage.value = data.current_page;
    } finally {
        loading.value = false;
    }
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchProducts();
}

async function fetchCategories() {
    const data = await apiGet('/api/categories');
    categories.value = data.categories;
}

function openCreate() {
    dialogMode.value = 'create';
    form.value = {
        category_id: null, name: '', barcode: '', type: 'barang',
        price: 0, cost_price: 0, stock: 0, min_stock: 0, unit: 'pcs', is_active: true,
    };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    form.value = { ...item, category_id: item.category_id };
    editingId.value = item.id;
    dialogVisible.value = true;
}

function openAdjust(item) {
    adjustProduct.value = item;
    adjustForm.value = { qty: 0, notes: '' };
    adjustDialogVisible.value = true;
}

async function save() {
    submitting.value = true;
    try {
        const isEdit = dialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/products/${editingId.value}`, form.value)
            : await apiPost('/api/products', form.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        dialogVisible.value = false;
        await fetchProducts();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function saveAdjust() {
    submitting.value = true;
    try {
        const data = await apiPost(`/api/products/${adjustProduct.value.id}/stock-adjust`, adjustForm.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        adjustDialogVisible.value = false;
        await fetchProducts();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

function confirmDeactivate(item) {
    confirm.require({
        message: `Nonaktifkan produk "${item.name}"?`,
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Nonaktifkan',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                const data = await apiDelete(`/api/products/${item.id}`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
                await fetchProducts();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

function formatCurrency(val) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
}

function isLowStock(item) {
    return item.type === 'barang' && item.stock <= item.min_stock;
}

onMounted(async () => {
    await Promise.all([fetchProducts(), fetchCategories()]);
});
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Manajemen Produk</h2>
            <Button label="Tambah Produk" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-4">
            <InputText v-model="searchQuery" placeholder="Cari nama / barcode..." class="w-64"
                @keyup.enter="fetchProducts" />
            <Select v-model="filterCategory" :options="categories" optionLabel="name" optionValue="id"
                placeholder="Semua Kategori" showClear class="w-48" @change="fetchProducts" />
            <Select v-model="filterType" :options="typeOptions" optionLabel="label" optionValue="value"
                placeholder="Semua Tipe" class="w-40" @change="fetchProducts" />
            <div class="flex items-center gap-2">
                <ToggleSwitch v-model="filterLowStock" @change="fetchProducts" />
                <label class="text-sm">Stok Rendah</label>
            </div>
            <Button icon="pi pi-search" severity="info" @click="fetchProducts" />
        </div>

        <DataTable :value="products" :loading="loading" stripedRows lazy paginator
            :rows="rowsPerPage" :totalRecords="totalRecords" :first="(currentPage - 1) * rowsPerPage"
            @page="onPageChange" dataKey="id" emptyMessage="Tidak ada produk ditemukan.">
            <Column header="No" style="width: 3rem">
                <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column field="name" header="Nama Produk" sortable />
            <Column field="barcode" header="Barcode" sortable style="width: 8rem" />
            <Column header="Kategori" sortable sortField="category.name" style="width: 8rem">
                <template #body="{ data }">{{ data.category?.name }}</template>
            </Column>
            <Column field="type" header="Tipe" sortable style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="data.type === 'barang' ? 'Barang' : 'Jasa'"
                        :severity="data.type === 'barang' ? 'info' : 'warn'" />
                </template>
            </Column>
            <Column header="Harga Jual" sortable sortField="price" style="width: 8rem">
                <template #body="{ data }">{{ formatCurrency(data.price) }}</template>
            </Column>
            <Column header="Stok" sortable sortField="stock" style="width: 6rem">
                <template #body="{ data }">
                    <span v-if="data.type === 'barang'" :class="{ 'text-red-500 font-bold': isLowStock(data) }">
                        {{ data.stock }} {{ data.unit }}
                        <i v-if="isLowStock(data)" class="pi pi-exclamation-triangle text-red-500 ml-1"></i>
                    </span>
                    <span v-else class="text-muted-color">—</span>
                </template>
            </Column>
            <Column header="Status" style="width: 5rem">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEdit(data)" />
                        <Button v-if="data.type === 'barang'" icon="pi pi-sort-alt" severity="warn" text rounded
                            size="small" v-tooltip="'Adjustment Stok'" @click="openAdjust(data)" />
                        <Button v-if="data.is_active" icon="pi pi-ban" severity="danger" text rounded size="small"
                            @click="confirmDeactivate(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Produk' : 'Edit Produk'"
            modal :style="{ width: '580px' }">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
                <div class="col-span-1 md:col-span-2 flex flex-col gap-2">
                    <label class="font-semibold">Nama Produk</label>
                    <InputText v-model="form.name" placeholder="Nama produk" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Kategori</label>
                    <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id"
                        placeholder="Pilih kategori" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Tipe</label>
                    <Select v-model="form.type" :options="[{label:'Barang',value:'barang'},{label:'Jasa',value:'jasa'}]"
                        optionLabel="label" optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Barcode</label>
                    <InputText v-model="form.barcode" placeholder="Opsional" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Satuan</label>
                    <InputText v-model="form.unit" placeholder="pcs, rim, lembar" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Harga Jual (Rp)</label>
                    <InputNumber v-model="form.price" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">HPP (Rp)</label>
                    <InputNumber v-model="form.cost_price" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div v-if="form.type === 'barang' && dialogMode === 'create'" class="flex flex-col gap-2">
                    <label class="font-semibold">Stok Awal</label>
                    <InputNumber v-model="form.stock" :min="0" />
                </div>
                <div v-if="form.type === 'barang'" class="flex flex-col gap-2">
                    <label class="font-semibold">Minimal Stok</label>
                    <InputNumber v-model="form.min_stock" :min="0" />
                </div>
                <div v-if="dialogMode === 'edit'" class="flex items-center gap-2 col-span-1 md:col-span-2">
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

        <!-- Stock Adjustment Dialog -->
        <Dialog v-model:visible="adjustDialogVisible" header="Adjustment Stok" modal :style="{ width: '420px' }">
            <div class="flex flex-col gap-4 pt-4" v-if="adjustProduct">
                <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <p class="m-0 font-semibold">{{ adjustProduct.name }}</p>
                    <p class="m-0 text-sm text-muted-color">Stok saat ini: {{ adjustProduct.stock }} {{ adjustProduct.unit }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Jumlah (+/-)</label>
                    <InputNumber v-model="adjustForm.qty" showButtons />
                    <small class="text-muted-color">Positif = tambah stok, Negatif = kurangi stok</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Catatan Alasan</label>
                    <InputText v-model="adjustForm.notes" placeholder="Misal: Restock dari supplier" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="adjustDialogVisible = false" />
                <Button label="Simpan" icon="pi pi-check" :loading="submitting" @click="saveAdjust" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
