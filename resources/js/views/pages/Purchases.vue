<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiDelete } from '@/composables/useApi';

const toast = useToast();
const confirm = useConfirm();

const purchases = ref([]);
const products = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const detailDialogVisible = ref(false);
const submitting = ref(false);
const selectedPurchase = ref(null);

const currentPage = ref(1);
const totalRecords = ref(0);

const form = ref({
    supplier_name: '',
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
        const data = await apiGet(`/api/purchases?page=${currentPage.value}`);
        purchases.value = data.data;
        totalRecords.value = data.total;
    } finally {
        loading.value = false;
    }
}

async function fetchProducts() {
    const data = await apiGet('/api/products?per_page=all');
    products.value = (Array.isArray(data) ? data : data.data || []).filter(p => p.type === 'barang');
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchPurchases();
}

function openCreate() {
    form.value = {
        supplier_name: '',
        purchase_date: new Date(),
        payment_status: 'paid',
        notes: '',
        items: [],
    };
    newItem.value = { product_id: null, qty: 1, unit_price: 0 };
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

    form.value.items.push({
        product_id: product.id,
        product_name: product.name,
        product_unit: product.unit,
        qty: newItem.value.qty,
        unit_price: newItem.value.unit_price || product.cost_price || 0,
        subtotal: newItem.value.qty * (newItem.value.unit_price || product.cost_price || 0),
    });

    newItem.value = { product_id: null, qty: 1, unit_price: 0 };
}

function removeFormItem(index) {
    form.value.items.splice(index, 1);
}

function recalcItem(item) {
    item.subtotal = item.qty * item.unit_price;
}

async function save() {
    if (form.value.items.length === 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Tambahkan minimal 1 item.', life: 3000 });
        return;
    }
    submitting.value = true;
    try {
        await apiPost('/api/purchases', {
            supplier_name: form.value.supplier_name || null,
            purchase_date: toApiDate(form.value.purchase_date),
            payment_status: form.value.payment_status,
            notes: form.value.notes || null,
            items: form.value.items.map(i => ({
                product_id: i.product_id,
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

function confirmDelete(purchase) {
    confirm.require({
        message: `Hapus pembelian ${purchase.purchase_number}?`,
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Hapus',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await apiDelete(`/api/purchases/${purchase.id}`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Data pembelian dihapus.', life: 3000 });
                await fetchPurchases();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

onMounted(async () => {
    await Promise.all([fetchPurchases(), fetchProducts()]);
});
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Pembelian Barang</h2>
            <Button label="Buat Pembelian" icon="pi pi-plus" @click="openCreate" />
        </div>

        <DataTable :value="purchases" :loading="loading" stripedRows lazy paginator
            :rows="20" :totalRecords="totalRecords" :first="(currentPage - 1) * 20"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada data pembelian.">
            <Column field="purchase_number" header="No. Pembelian" sortable style="width: 10rem" />
            <Column header="Tanggal" style="width: 8rem">
                <template #body="{ data }">{{ formatDate(data.purchase_date) }}</template>
            </Column>
            <Column field="supplier_name" header="Supplier">
                <template #body="{ data }">{{ data.supplier_name || '—' }}</template>
            </Column>
            <Column header="Total" style="width: 9rem">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatRp(data.total_amount) }}</span>
                </template>
            </Column>
            <Column header="Status" style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="data.payment_status === 'paid' ? 'Lunas' : 'Belum'"
                        :severity="data.payment_status === 'paid' ? 'success' : 'warn'" />
                </template>
            </Column>
            <Column header="Dicatat Oleh" style="width: 7rem">
                <template #body="{ data }">{{ data.user?.name }}</template>
            </Column>
            <Column header="Aksi" style="width: 7rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded size="small" @click="viewDetail(data)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create Dialog -->
        <Dialog v-model:visible="dialogVisible" header="Buat Pembelian Baru" modal
            :style="{ width: '700px' }" :breakpoints="{ '768px': '95vw' }">
            <div class="flex flex-col gap-4 pt-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Nama Supplier</label>
                        <InputText v-model="form.supplier_name" placeholder="Opsional" />
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
                        <div class="flex flex-col gap-1 flex-1 min-w-[150px]">
                            <label class="text-xs text-muted-color">Produk</label>
                            <Select v-model="newItem.product_id" :options="products" optionLabel="name" optionValue="id"
                                placeholder="Pilih produk" filter showClear />
                        </div>
                        <div class="flex flex-col gap-1 w-24">
                            <label class="text-xs text-muted-color">Qty</label>
                            <InputNumber v-model="newItem.qty" :min="1" showButtons size="small" />
                        </div>
                        <div class="flex flex-col gap-1 w-40">
                            <label class="text-xs text-muted-color">Harga Beli</label>
                            <InputNumber v-model="newItem.unit_price" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                        </div>
                        <Button icon="pi pi-plus" severity="success" @click="addItem" />
                    </div>
                </div>

                <!-- Item List -->
                <DataTable :value="form.items" dataKey="product_id" class="p-datatable-sm"
                    emptyMessage="Belum ada item.">
                    <Column field="product_name" header="Produk" />
                    <Column header="Qty" style="width: 6rem">
                        <template #body="{ data, index }">
                            <InputNumber v-model="data.qty" :min="1" showButtons size="small"
                                class="w-24" @update:modelValue="recalcItem(data)" />
                        </template>
                    </Column>
                    <Column header="Harga Beli" style="width: 8rem">
                        <template #body="{ data }">
                            <InputNumber v-model="data.unit_price" :min="0" mode="currency" currency="IDR"
                                locale="id-ID" size="small" @update:modelValue="recalcItem(data)" />
                        </template>
                    </Column>
                    <Column header="Subtotal" style="width: 7rem">
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
                        <p class="m-0 font-semibold">{{ selectedPurchase.supplier_name || '—' }}</p></div>
                    <div><span class="text-sm text-muted-color">Status</span>
                        <p class="m-0"><Tag :value="selectedPurchase.payment_status === 'paid' ? 'Lunas' : 'Belum'"
                            :severity="selectedPurchase.payment_status === 'paid' ? 'success' : 'warn'" /></p></div>
                </div>

                <DataTable :value="selectedPurchase.items" dataKey="id" class="p-datatable-sm">
                    <Column header="Produk">
                        <template #body="{ data }">{{ data.product?.name }}</template>
                    </Column>
                    <Column field="qty" header="Qty" style="width: 5rem">
                        <template #body="{ data }">{{ data.qty }} {{ data.product?.unit }}</template>
                    </Column>
                    <Column header="Harga Beli" style="width: 7rem">
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
