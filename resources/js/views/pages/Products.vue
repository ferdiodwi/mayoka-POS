<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const confirm = useConfirm();
const { hasPermission } = useAuth();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const adjustDialogVisible = ref(false);
const importDialogVisible = ref(false);
const dialogMode = ref('create');
const submitting = ref(false);
const uploading = ref(false);
const importFile = ref(null);
const fileInput = ref(null);
const editingId = ref(null);

const stockBreakdownText = computed(() => {
    if (dialogMode.value !== 'edit' || form.value.type !== 'barang' || !form.value.units || form.value.units.length === 0) return '';
    
    let remainingStock = form.value.stock || 0;
    const breakdown = [];
    const baseUnitName = form.value.units[0].unit_name || 'PCS';
    const totalStock = remainingStock;
    
    // Reverse loop to start from highest level
    for (let i = form.value.units.length - 1; i >= 0; i--) {
        const unit = form.value.units[i];
        if (unit.base_multiplier > 0 && unit.unit_name) {
            const qty = Math.floor(remainingStock / unit.base_multiplier);
            if (qty > 0) {
                breakdown.push(`${qty} ${unit.unit_name}`);
                remainingStock -= (qty * unit.base_multiplier);
            }
        }
    }
    
    if (breakdown.length === 0) return `0 ${baseUnitName}`;
    
    return `Sisa ${breakdown.join(' ')} (Total stok: ${totalStock} ${baseUnitName})`;
});

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
    category_id: null, name: '', product_code: '', barcode: '', type: 'barang',
    cost_price: 0, stock: 0, min_stock: 0, is_active: true,
    units: [
        { level: 1, unit_name: 'PCS', qty_per_previous: 1, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
        { level: 2, unit_name: '', qty_per_previous: 1, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
        { level: 3, unit_name: '', qty_per_previous: 1, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
        { level: 4, unit_name: '', qty_per_previous: 1, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 }
    ]
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
        category_id: null, name: '', product_code: '', barcode: '', type: 'barang',
        cost_price: 0, stock: 0, min_stock: 0, is_active: true,
        units: [
            { level: 1, unit_name: 'PCS', qty_per_previous: 1, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
            { level: 2, unit_name: '', qty_per_previous: 0, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
            { level: 3, unit_name: '', qty_per_previous: 0, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 },
            { level: 4, unit_name: '', qty_per_previous: 0, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 }
        ]
    };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    const existingUnits = item.units || [];
    const unitsData = [];
    for (let i = 1; i <= 4; i++) {
        const u = existingUnits.find(x => x.level === i);
        if (u) {
            unitsData.push({ ...u });
        } else {
            unitsData.push({ level: i, unit_name: '', qty_per_previous: 0, base_multiplier: 1, price_h1: 0, price_h2: 0, price_h3: 0 });
        }
    }
    form.value = { ...item, category_id: item.category_id, units: unitsData, last_cost_price: item.last_cost_price || 0 };
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

function updateMultipliers() {
    let currentMultiplier = 1;
    for (let i = 0; i < 4; i++) {
        if (i === 0) {
            form.value.units[i].base_multiplier = 1;
            form.value.units[i].qty_per_previous = 1;
        } else {
            currentMultiplier = currentMultiplier * (form.value.units[i].qty_per_previous || 1);
            form.value.units[i].base_multiplier = currentMultiplier;
        }
    }
}

function formatHierarchicalStock(totalBaseQty, units) {
    if (!units || units.length === 0) return totalBaseQty;
    
    // Sort units by base_multiplier descending
    const sortedUnits = [...units].sort((a, b) => b.base_multiplier - a.base_multiplier);
    
    let remaining = totalBaseQty;
    let parts = [];
    
    for (const u of sortedUnits) {
        if (!u.unit_name || u.base_multiplier <= 0) continue;
        const qty = Math.floor(remaining / u.base_multiplier);
        if (qty > 0) {
            parts.push(`${qty} ${u.unit_name}`);
            remaining = remaining % u.base_multiplier;
        }
    }
    
    const base = sortedUnits.find(u => u.level === 1);
    const baseUnitName = base ? base.unit_name : '';

    if (parts.length === 0) {
        return `0 ${baseUnitName} (Total: 0 ${baseUnitName})`;
    }
    
    return parts.join(' ') + ` (Total: ${totalBaseQty} ${baseUnitName})`;
}

// Intercept save to calculate multipliers and filter empty units
const originalSave = save;
save = async function() {
    updateMultipliers();
    // Validate units: At least level 1 must have name and > 0 price
    const validUnits = form.value.units.filter(u => u.unit_name && u.unit_name.trim() !== '');
    if (validUnits.length === 0) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Satuan terkecil harus diisi', life: 3000 });
        return;
    }
    
    // Temporarily replace form.units with valid ones for submission
    const allUnits = form.value.units;
    form.value.units = validUnits;
    await originalSave();
    // Form is usually reset or closed after save, but if it fails we might need to restore
    if (dialogVisible.value) {
        form.value.units = allUnits;
    }
};

function isLowStock(item) {
    return item.type === 'barang' && item.stock <= item.min_stock;
}

function openImport() {
    importFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
    importDialogVisible.value = true;
}

function handleFileChange(e) {
    importFile.value = e.target.files[0];
}

async function downloadTemplate() {
    window.location.href = '/api/products/template';
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

        // Extract CSRF token
        const tokenMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = tokenMatch ? decodeURIComponent(tokenMatch[1]) : '';
        const branchId = localStorage.getItem('activeBranchId');

        const res = await fetch('/api/products/import', {
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

        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Data produk berhasil diimport.', life: 3000 });
        importDialogVisible.value = false;
        fetchProducts();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        uploading.value = false;
    }
}

onMounted(async () => {
    fetchCategories();
    fetchProducts();

    if (window.Echo) {
        window.Echo.channel('pos-channel')
            .listen('ProductStockUpdated', (e) => {
                const prod = products.value.find(p => p.id === e.productId);
                if (prod) {
                    prod.stock = e.newStock;
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('pos-channel');
    }
});
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Manajemen Produk</h2>
            <div class="flex gap-2">
                <Button v-if="hasPermission('products.create')" label="Import Excel" icon="pi pi-upload" severity="secondary" outlined @click="openImport" />
                <Button v-if="hasPermission('products.create')" label="Tambah Produk" icon="pi pi-plus" @click="openCreate" />
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-4">
            <InputText v-model="searchQuery" placeholder="Cari nama / kode / barcode..." class="w-64"
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
            <Column field="product_code" header="Kode Barang" sortable style="width: 8rem" />
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
            <Column header="Harga Jual" sortable style="width: 8rem">
                <template #body="{ data }">
                    <div v-if="data.units && data.units.length > 0">
                        {{ formatCurrency(data.units[0].price_h1) }}
                    </div>
                    <div v-else>0</div>
                </template>
            </Column>
            <Column header="Stok" sortable sortField="stock" style="min-width: 12rem">
                <template #body="{ data }">
                    <span v-if="data.type === 'barang'" :class="{ 'text-red-500 font-bold': isLowStock(data) }">
                        {{ formatHierarchicalStock(data.stock, data.units) }}
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
            <Column header="Aksi" style="width: 10rem" v-if="hasPermission('products.update') || hasPermission('products.delete')">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="hasPermission('products.update')" icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEdit(data)" />
                        <Button v-if="hasPermission('products.update') && data.type === 'barang'" icon="pi pi-sort-alt" severity="warn" text rounded
                            size="small" v-tooltip="'Adjustment Stok'" @click="openAdjust(data)" />
                        <Button v-if="hasPermission('products.delete') && data.is_active" icon="pi pi-ban" severity="danger" text rounded size="small"
                            @click="confirmDeactivate(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Produk' : 'Edit Produk'"
            modal :style="{ width: '1000px' }" :breakpoints="{ '1024px': '95vw' }">
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
                    <label class="font-semibold">Kode Barang</label>
                    <InputText :value="dialogMode === 'create' ? 'Dibuat Otomatis' : form.product_code" disabled placeholder="Dibuat otomatis" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Barcode</label>
                    <InputText v-model="form.barcode" placeholder="Opsional" />
                </div>
                <div v-if="form.type === 'barang' && dialogMode === 'create'" class="flex flex-col gap-2 md:col-span-1">
                    <label class="font-semibold">Stok Awal (Satuan Terkecil)</label>
                    <InputNumber v-model="form.stock" :min="0" />
                </div>
                <div v-else-if="form.type === 'barang' && dialogMode === 'edit'" class="flex flex-col gap-2 md:col-span-1">
                    <label class="font-semibold">Informasi Stok</label>
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-blue-700 dark:text-blue-300">
                        <i class="pi pi-box mr-2"></i>
                        <span class="font-semibold">{{ stockBreakdownText }}</span>
                    </div>
                </div>

                <div v-if="form.type === 'barang'" class="flex flex-col gap-2 md:col-span-1">
                    <label class="font-semibold">Minimal Stok (Satuan Terkecil)</label>
                    <InputNumber v-model="form.min_stock" :min="0" />
                </div>
                
                <!-- Units Grid -->
                <div class="col-span-1 md:col-span-2 mt-4" v-if="form.type === 'barang' || form.type === 'jasa'">
                    <h3 class="font-semibold text-lg border-b pb-2 mb-4">Pengaturan Satuan & Harga Jual</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">Tingkat</th>
                                    <th class="text-left p-2">Nama Satuan</th>
                                    <th class="text-left p-2">Isi per Satuan Bawah</th>
                                    <th class="text-right p-2">H1 (Ecer)</th>
                                    <th class="text-right p-2">H2 (Grosir)</th>
                                    <th class="text-right p-2">H3 (Khusus)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Level 1 -->
                                <tr class="border-b">
                                    <td class="p-2 font-semibold text-center">1 (Satu)</td>
                                    <td class="p-2">
                                        <InputText v-model="form.units[0].unit_name" class="w-full min-w-[80px]" placeholder="PCS" />
                                    </td>
                                    <td class="p-2 text-center text-muted-color font-bold">1</td>
                                    <td class="p-2"><InputNumber v-model="form.units[0].price_h1" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[0].price_h2" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[0].price_h3" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                </tr>
                                <!-- Level 2 -->
                                <tr class="border-b">
                                    <td class="p-2 font-semibold text-center">1 (Satu)</td>
                                    <td class="p-2">
                                        <InputText v-model="form.units[1].unit_name" class="w-full min-w-[80px]" placeholder="PCK" />
                                    </td>
                                    <td class="p-2 flex items-center gap-2">
                                        <span class="font-bold">=</span>
                                        <InputNumber v-model="form.units[1].qty_per_previous" class="w-16" :min="0" />
                                        <span class="text-muted-color">{{ form.units[0].unit_name || 'PCS' }}</span>
                                    </td>
                                    <td class="p-2"><InputNumber v-model="form.units[1].price_h1" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[1].price_h2" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[1].price_h3" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                </tr>
                                <!-- Level 3 -->
                                <tr class="border-b">
                                    <td class="p-2 font-semibold text-center">1 (Satu)</td>
                                    <td class="p-2">
                                        <InputText v-model="form.units[2].unit_name" class="w-full min-w-[80px]" placeholder="DOS" />
                                    </td>
                                    <td class="p-2 flex items-center gap-2">
                                        <span class="font-bold">=</span>
                                        <InputNumber v-model="form.units[2].qty_per_previous" class="w-16" :min="0" />
                                        <span class="text-muted-color">{{ form.units[1].unit_name || 'PCK' }}</span>
                                    </td>
                                    <td class="p-2"><InputNumber v-model="form.units[2].price_h1" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[2].price_h2" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[2].price_h3" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                </tr>
                                <!-- Level 4 -->
                                <tr class="border-b">
                                    <td class="p-2 font-semibold text-center">1 (Satu)</td>
                                    <td class="p-2">
                                        <InputText v-model="form.units[3].unit_name" class="w-full min-w-[80px]" />
                                    </td>
                                    <td class="p-2 flex items-center gap-2">
                                        <span class="font-bold">=</span>
                                        <InputNumber v-model="form.units[3].qty_per_previous" class="w-16" :min="0" />
                                        <span class="text-muted-color">{{ form.units[2].unit_name || 'DOS' }}</span>
                                    </td>
                                    <td class="p-2"><InputNumber v-model="form.units[3].price_h1" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[3].price_h2" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                    <td class="p-2"><InputNumber v-model="form.units[3].price_h3" class="w-full min-w-[100px]" mode="currency" currency="IDR" locale="id-ID" :min="0" /></td>
                                </tr>
                            </tbody>
                        </table>
                        <small class="text-muted-color mt-2 block">*Kosongkan nama satuan jika tidak digunakan.</small>
                    </div>
                </div>
                <div class="col-span-1 md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-if="dialogMode === 'edit'" class="flex flex-col gap-2">
                        <label class="font-semibold text-muted-color">HPP Lama (Sebelum Kulakan)</label>
                        <div class="p-2 px-3 bg-surface-100 dark:bg-surface-800 rounded-lg border border-surface-200 dark:border-surface-700 font-bold text-lg text-surface-600 dark:text-surface-300 flex items-center h-[42px]">
                            {{ formatCurrency(form.last_cost_price || 0) }}
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-2" :class="dialogMode === 'edit' ? '' : 'md:col-span-2'">
                        <label class="font-semibold">{{ dialogMode === 'edit' ? 'HPP Baru (Saat Ini)' : 'HPP (Modal Dasar)' }}</label>
                        <InputNumber v-model="form.cost_price" mode="currency" currency="IDR" locale="id-ID" :min="0" class="!text-lg font-bold" />
                    </div>
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
                    <p class="m-0 text-sm text-muted-color">Stok saat ini: {{ formatHierarchicalStock(adjustProduct.stock, adjustProduct.units) }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Jumlah (+/-)</label>
                    <InputNumber v-model="adjustForm.qty" showButtons />
                    <small class="text-muted-color">Positif = tambah stok, Negatif = kurangi stok</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Catatan Alasan</label>
                    <InputText v-model="adjustForm.notes" placeholder="Misal: Barang rusak / hilang" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="adjustDialogVisible = false" />
                <Button label="Simpan" icon="pi pi-check" :loading="submitting" @click="saveAdjust" />
            </template>
        </Dialog>

        <!-- Import Excel Dialog -->
        <Dialog v-model:visible="importDialogVisible" header="Import Produk (Excel)" modal :style="{ width: '500px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="m-0 text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Instruksi Import:</p>
                    <ol class="m-0 pl-4 text-sm text-blue-600 dark:text-blue-400 space-y-1">
                        <li>Download template Excel.</li>
                        <li>Isi data produk sesuai format kolom. Jangan ubah nama kolom di baris pertama.</li>
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
