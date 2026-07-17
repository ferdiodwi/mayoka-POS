<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPatch } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const confirm = useConfirm();
const { hasPermission } = useAuth();

const purchases = ref([]);
const products = ref([]);
const suppliersList = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const detailDialogVisible = ref(false);
const submitting = ref(false);
const selectedPurchase = ref(null);

const currentPage = ref(1);
const totalRecords = ref(0);

// Filters
const filterDateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const filterDateTo = ref(new Date());
const filterSupplier = ref('');

const form = ref({
    supplier_input: null,
    purchase_date: new Date(),
    payment_status: 'paid',
    notes: '',
    items: [],
});

const newItem = ref({ product_id: null, qty: 1, unit_price: 0 });

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

const formTotal = computed(() => form.value.items.reduce((s, i) => s + i.subtotal, 0));

async function fetchPurchases() {
    loading.value = true;
    try {
        let url = `/api/purchases?page=${currentPage.value}`;
        if (filterDateFrom.value) url += `&date_from=${toApiDate(filterDateFrom.value)}`;
        if (filterDateTo.value) url += `&date_to=${toApiDate(filterDateTo.value)}`;
        if (filterSupplier.value) url += `&supplier=${encodeURIComponent(filterSupplier.value)}`;
        
        const data = await apiGet(url);
        purchases.value = data.data;
        totalRecords.value = data.total;
    } finally {
        loading.value = false;
    }
}

function applyFilter() {
    currentPage.value = 1;
    fetchPurchases();
}

function clearFilter() {
    filterDateFrom.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    filterDateTo.value = new Date();
    filterSupplier.value = '';
    applyFilter();
}

function exportReport(format) {
    let url = `/api/purchases/export?format=${format}`;
    if (filterDateFrom.value) url += `&date_from=${toApiDate(filterDateFrom.value)}`;
    if (filterDateTo.value) url += `&date_to=${toApiDate(filterDateTo.value)}`;
    if (filterSupplier.value) url += `&supplier=${encodeURIComponent(filterSupplier.value)}`;
    window.open(url, '_blank');
}

async function fetchProducts() {
    const data = await apiGet('/api/products?per_page=all');
    products.value = (Array.isArray(data) ? data : data.data || []).filter(p => p.type === 'barang');
}

async function fetchSuppliers() {
    const data = await apiGet('/api/suppliers?per_page=all');
    suppliersList.value = (data.suppliers || []).filter(s => s.is_active);
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchPurchases();
}

function openCreate() {
    form.value = {
        supplier_input: null,
        purchase_date: new Date(),
        payment_status: 'paid',
        notes: '',
        items: [],
    };
    newItem.value = { product_id: null };
    dialogVisible.value = true;
}

function addItem() {
    if (!newItem.value.product_id) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Pilih produk.', life: 2000 });
        return;
    }
    const product = products.value.find(p => p.id === newItem.value.product_id);
    if (!product) return;

    // Check if product already exists in list
    const existing = form.value.items.find(i => i.product_id === newItem.value.product_id);
    if (existing) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Produk sudah ada di daftar.', life: 2000 });
        return;
    }

    const baseUnit = product.units && product.units.length > 0 ? product.units[0] : null;
    const baseMultiplier = baseUnit ? baseUnit.base_multiplier : 1;
    const defaultPrice = 0;
    
    form.value.items.push({
        product_id: product.id,
        product_name: product.name,
        units: product.units || [],
        unit_name: baseUnit ? baseUnit.unit_name : 'PCS',
        base_multiplier: baseMultiplier,
        qty: 1,
        unit_price: 0,
        price_per_pcs: 0,
        subtotal: 0,
    });

    newItem.value = { product_id: null };
}

function removeFormItem(index) {
    form.value.items.splice(index, 1);
}

function handleUnitChange(item) {
    const selectedUnit = item.units.find(u => u.unit_name === item.unit_name);
    if (selectedUnit) {
        item.base_multiplier = selectedUnit.base_multiplier;
        recalcItem(item);
    }
}

function recalcItem(item) {
    item.subtotal = item.qty * item.unit_price;
    item.price_per_pcs = (item.unit_price || 0) / (item.base_multiplier || 1);
}

async function save() {
    if (form.value.items.length === 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Tambahkan minimal 1 item.', life: 3000 });
        return;
    }
    submitting.value = true;
    try {
        let finalSupplierId = null;
        let finalSupplierName = null;

        if (typeof form.value.supplier_input === 'object' && form.value.supplier_input !== null) {
            finalSupplierId = form.value.supplier_input.id;
            finalSupplierName = null; // Backend uses name from relation if id exists
        } else if (typeof form.value.supplier_input === 'string' && form.value.supplier_input.trim() !== '') {
            const typedName = form.value.supplier_input.trim();
            const found = suppliersList.value.find(s => s.name.toLowerCase() === typedName.toLowerCase());
            if (found) {
                finalSupplierId = found.id;
                finalSupplierName = null;
            } else {
                finalSupplierName = typedName;
            }
        }

        await apiPost('/api/purchases', {
            supplier_id: finalSupplierId,
            supplier_name: finalSupplierName,
            purchase_date: toApiDate(form.value.purchase_date),
            payment_status: form.value.payment_status,
            notes: form.value.notes || null,
            items: form.value.items.map(i => ({
                product_id: i.product_id,
                unit_name: i.unit_name,
                base_multiplier: i.base_multiplier,
                qty: i.qty,
                unit_price: i.unit_price,
            })),
        });
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pembelian berhasil disimpan. Stok terupdate.', life: 3000 });
        dialogVisible.value = false;
        await fetchPurchases();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function viewDetail(purchase) {
    try {
        const data = await apiGet(`/api/purchases/${purchase.id}`);
        selectedPurchase.value = data.purchase;
        detailDialogVisible.value = true;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 3000 });
    }
}

function confirmVoid(purchase) {
    confirm.require({
        message: `Batalkan pembelian ${purchase.purchase_number}? Stok yang sudah masuk akan dikurangi kembali.`,
        header: 'Konfirmasi Pembatalan',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Tidak',
        acceptLabel: 'Ya, Batalkan',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await apiPost(`/api/purchases/${purchase.id}/void`, { reason: 'Dibatalkan oleh admin' });
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pembelian dibatalkan. Stok dikembalikan.', life: 3000 });
                await fetchPurchases();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

function confirmMarkPaid(purchase) {
    confirm.require({
        message: `Tandai pembelian ${purchase.purchase_number} sebagai Lunas?`,
        header: 'Konfirmasi Pelunasan',
        icon: 'pi pi-check-circle',
        rejectLabel: 'Batal',
        acceptLabel: 'Ya, Lunas',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await apiPatch(`/api/purchases/${purchase.id}/mark-paid`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pembelian telah dilunasi.', life: 3000 });
                await fetchPurchases();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

onMounted(async () => {
    await Promise.all([fetchPurchases(), fetchProducts(), fetchSuppliers()]);
});
</script>

<template>
    <div class="card">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
            <h2 class="text-2xl font-semibold m-0">Pembelian Barang</h2>
            <Button v-if="hasPermission('purchases.create')" label="Buat Pembelian" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4 mb-6 p-4 bg-surface-50 dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700">
            <div class="flex flex-col gap-1 w-full sm:w-auto">
                <label class="text-sm font-semibold text-muted-color">Dari Tanggal</label>
                <DatePicker v-model="filterDateFrom" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1 w-full sm:w-auto">
                <label class="text-sm font-semibold text-muted-color">Sampai Tanggal</label>
                <DatePicker v-model="filterDateTo" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1 w-full sm:w-auto flex-1 min-w-[200px]">
                <label class="text-sm font-semibold text-muted-color">Pemasok / Supplier</label>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filterSupplier" placeholder="Cari nama supplier..." class="w-full" @keyup.enter="applyFilter" />
                </IconField>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <Button label="Cari" icon="pi pi-search" @click="applyFilter" />
                <Button label="Reset" icon="pi pi-filter-slash" severity="secondary" outlined @click="clearFilter" />
            </div>
            <div class="ml-auto flex gap-2">
                <Button label="Excel" icon="pi pi-file-excel" severity="success" outlined @click="exportReport('excel')" />
                <Button label="PDF" icon="pi pi-file-pdf" severity="danger" outlined @click="exportReport('pdf')" />
            </div>
        </div>

        <DataTable :value="purchases" :loading="loading" stripedRows lazy paginator
            :rows="20" :totalRecords="totalRecords" :first="(currentPage - 1) * 20"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada data pembelian.">
            <Column field="purchase_number" header="No. Pembelian" sortable style="width: 10rem" />
            <Column header="Tanggal" style="width: 8rem">
                <template #body="{ data }">{{ formatDate(data.purchase_date) }}</template>
            </Column>
            <Column field="supplier_name" header="Supplier">
                <template #body="{ data }">{{ data.supplier?.name || data.supplier_name || '—' }}</template>
            </Column>
            <Column header="Total" style="width: 9rem">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatRp(data.total_amount) }}</span>
                </template>
            </Column>
            <Column header="Status" style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="data.payment_status === 'paid' ? 'Lunas' : data.payment_status === 'voided' ? 'Dibatalkan' : 'Belum'"
                        :severity="data.payment_status === 'paid' ? 'success' : data.payment_status === 'voided' ? 'danger' : 'warn'" />
                </template>
            </Column>
            <Column header="Dicatat Oleh" style="width: 7rem">
                <template #body="{ data }">{{ data.user?.name }}</template>
            </Column>
            <Column header="Aksi" style="width: 9rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded size="small" @click="viewDetail(data)" v-tooltip="'Detail'" />
                        <Button v-if="hasPermission('purchases.update') && data.payment_status === 'unpaid'" icon="pi pi-check-circle" severity="success" text rounded size="small" @click="confirmMarkPaid(data)" v-tooltip="'Tandai Lunas'" />
                        <Button v-if="hasPermission('purchases.delete') && data.payment_status !== 'voided'" icon="pi pi-ban" severity="danger" text rounded size="small" @click="confirmVoid(data)" v-tooltip="'Batalkan'" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create Dialog -->
        <Dialog v-model:visible="dialogVisible" header="Buat Pembelian Baru" modal
            :style="{ width: '1000px' }" :breakpoints="{ '1024px': '90vw', '768px': '95vw' }">
            <div class="flex flex-col gap-4 pt-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Supplier</label>
                        <Select v-model="form.supplier_input" :options="suppliersList" optionLabel="name"
                            placeholder="Pilih atau ketik nama supplier..." editable class="w-full" showClear />
                        <small class="text-muted-color text-xs">Pilih dari daftar atau langsung ketik nama supplier baru.</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Tanggal Pembelian</label>
                        <DatePicker v-model="form.purchase_date" dateFormat="dd/mm/yy" showIcon />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Status Bayar</label>
                        <Select v-model="form.payment_status"
                            :options="[{label:'Lunas',value:'paid'},{label:'Belum Lunas',value:'unpaid'}]"
                            optionLabel="label" optionValue="value" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Catatan</label>
                        <InputText v-model="form.notes" placeholder="Opsional" />
                    </div>
                </div>

                <!-- Add Item -->
                <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <label class="font-semibold block mb-3">Tambah Produk</label>
                    <div class="flex flex-wrap gap-2 items-end">
                        <div class="flex flex-col gap-1 flex-1">
                            <Select v-model="newItem.product_id" :options="products" optionLabel="name" optionValue="id"
                                placeholder="Cari dan pilih produk..." filter showClear 
                                @change="addItem" @keyup.enter="addItem" />
                        </div>
                    </div>
                </div>

                <!-- Item List -->
                <DataTable :value="form.items" dataKey="product_id"
                    emptyMessage="Belum ada item.">
                    <Column field="product_name" header="Produk" />
                    <Column header="Satuan" style="width: 12rem">
                        <template #body="{ data }">
                            <Select v-if="data.units && data.units.length > 0" v-model="data.unit_name" :options="data.units.filter(u => u.unit_name)" optionLabel="unit_name" optionValue="unit_name" class="w-full" @change="handleUnitChange(data)">
                                <template #option="slotProps">
                                    <div class="flex justify-between w-full">
                                        <span>{{ slotProps.option.unit_name }}</span>
                                        <span class="text-muted-color text-sm ml-2">Isi {{ slotProps.option.base_multiplier }}</span>
                                    </div>
                                </template>
                            </Select>
                            <span v-else>PCS <small class="text-muted-color">(Isi 1)</small></span>
                            <div v-if="data.units && data.units.length > 0" class="text-xs text-blue-500 font-medium mt-1">Isi: {{ data.base_multiplier }} Dasar</div>
                        </template>
                    </Column>
                    <Column header="Qty" style="width: 8rem">
                        <template #body="{ data, index }">
                            <InputNumber :modelValue="data.qty" :min="1" showButtons 
                                class="w-24" @input="(e) => { data.qty = e.value; recalcItem(data); }" />
                        </template>
                    </Column>
                    <Column header="Harga (Input)" style="width: 12rem">
                        <template #body="{ data }">
                            <InputNumber :modelValue="data.unit_price" :min="0" mode="currency" currency="IDR"
                                locale="id-ID" class="w-full" @input="(e) => { data.unit_price = e.value; recalcItem(data); }" />
                        </template>
                    </Column>
                    <Column header="Harga / Pcs" style="width: 9rem">
                        <template #body="{ data }">
                            <span class="text-muted-color font-semibold">{{ formatRp(data.price_per_pcs || 0) }}</span>
                        </template>
                    </Column>
                    <Column header="Subtotal" style="width: 9rem">
                        <template #body="{ data }">{{ formatRp(data.subtotal) }}</template>
                    </Column>
                    <Column style="width: 3rem">
                        <template #body="{ index }">
                            <Button icon="pi pi-times" severity="danger" text rounded size="small"
                                @click="removeFormItem(index)" />
                        </template>
                    </Column>
                </DataTable>

                <!-- Total -->
                <div class="flex justify-end p-3 bg-primary/10 rounded-lg">
                    <span class="text-xl font-bold">Total: {{ formatRp(formTotal) }}</span>
                </div>
            </div>

            <template #footer>
                <Button label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button label="Simpan" icon="pi pi-check" :loading="submitting" @click="save" />
            </template>
        </Dialog>

        <!-- Detail Dialog -->
        <Dialog v-model:visible="detailDialogVisible" header="Detail Pembelian" modal
            :style="{ width: '600px' }" :breakpoints="{ '768px': '95vw' }">
            <div v-if="selectedPurchase" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4 p-3 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <div><span class="text-sm text-muted-color">No. Pembelian</span>
                        <p class="m-0 font-semibold">{{ selectedPurchase.purchase_number }}</p></div>
                    <div><span class="text-sm text-muted-color">Tanggal</span>
                        <p class="m-0 font-semibold">{{ formatDate(selectedPurchase.purchase_date) }}</p></div>
                    <div><span class="text-sm text-muted-color">Supplier</span>
                        <p class="m-0 font-semibold">{{ selectedPurchase.supplier?.name || selectedPurchase.supplier_name || '—' }}</p></div>
                    <div><span class="text-sm text-muted-color">Status</span>
                        <p class="m-0"><Tag :value="selectedPurchase.payment_status === 'paid' ? 'Lunas' : 'Belum'"
                            :severity="selectedPurchase.payment_status === 'paid' ? 'success' : 'warn'" /></p></div>
                </div>

                <DataTable :value="selectedPurchase.items" dataKey="id" class="p-datatable-sm">
                    <Column field="product.name" header="Produk" />
                    <Column header="Qty" style="width: 8rem">
                        <template #body="{ data }">{{ data.qty }} {{ data.unit_name }}</template>
                    </Column>
                    <Column header="Harga" style="width: 8rem">
                        <template #body="{ data }">{{ formatRp(data.unit_price) }}</template>
                    </Column>
                    <Column header="Subtotal" style="width: 7rem">
                        <template #body="{ data }">{{ formatRp(data.subtotal) }}</template>
                    </Column>
                </DataTable>

                <div class="flex justify-end p-3 bg-primary/10 rounded-lg">
                    <span class="text-xl font-bold">Total: {{ formatRp(selectedPurchase.total_amount) }}</span>
                </div>

                <p v-if="selectedPurchase.notes" class="m-0 text-sm text-muted-color">
                    <i class="pi pi-info-circle mr-1"></i> {{ selectedPurchase.notes }}
                </p>
            </div>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
