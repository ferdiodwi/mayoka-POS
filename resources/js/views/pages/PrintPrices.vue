<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const { hasPermission } = useAuth();

const printPrices = ref([]);
const loading = ref(false);
const submitting = ref(false);

// Price dialog
const priceDialogVisible = ref(false);
const priceDialogMode = ref('create');
const priceForm = ref({ paper_size: 'A4', color_type: 'bw', side_type: 'single', price_per_sheet: 0, cost_per_sheet: 0 });
const editingPriceId = ref(null);

// Tier dialog
const tierDialogVisible = ref(false);
const tierDialogMode = ref('create');
const tierForm = ref({ min_qty: 0, price_per_sheet: 0 });
const editingTierId = ref(null);
const tierParentId = ref(null);

const paperSizes = [{ label: 'A4', value: 'A4' }, { label: 'F4', value: 'F4' }, { label: 'A3', value: 'A3' }];
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

// --- Tier CRUD ---
function openCreateTier(priceId) {
    tierDialogMode.value = 'create';
    tierForm.value = { min_qty: 50, price_per_sheet: 0 };
    tierParentId.value = priceId;
    editingTierId.value = null;
    tierDialogVisible.value = true;
}

function openEditTier(tier) {
    tierDialogMode.value = 'edit';
    tierForm.value = { min_qty: tier.min_qty, price_per_sheet: parseFloat(tier.price_per_sheet) };
    editingTierId.value = tier.id;
    tierDialogVisible.value = true;
}

async function saveTier() {
    submitting.value = true;
    try {
        const isEdit = tierDialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/print-price-tiers/${editingTierId.value}`, tierForm.value)
            : await apiPost(`/api/print-prices/${tierParentId.value}/tiers`, tierForm.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        tierDialogVisible.value = false;
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function deleteTier(tier) {
    try {
        const data = await apiDelete(`/api/print-price-tiers/${tier.id}`);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

// --- Expanded rows ---
const expandedRows = ref({});

onMounted(fetchData);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Harga Cetak</h2>
            <Button v-if="hasPermission('print_prices.create')" label="Tambah Kombinasi" icon="pi pi-plus" @click="openCreatePrice" />
        </div>

        <DataTable :value="printPrices" :loading="loading" v-model:expandedRows="expandedRows"
            dataKey="id" stripedRows emptyMessage="Belum ada data harga cetak.">
            <Column expander style="width: 3rem" />
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
            <Column header="Tier Grosir" style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="`${data.tiers?.length || 0} tier`" severity="info" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 8rem" v-if="hasPermission('print_prices.update') || hasPermission('print_prices.delete')">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="hasPermission('print_prices.update')" icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEditPrice(data)" />
                        <Button v-if="hasPermission('print_prices.delete')" icon="pi pi-trash" severity="danger" text rounded size="small" @click="deletePrice(data)" />
                    </div>
                </template>
            </Column>

            <!-- Expanded row: Tier list -->
            <template #expansion="{ data }">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="m-0 text-lg">Tier Harga Grosir — {{ data.paper_size }} {{ colorLabel(data.color_type) }}</h4>
                        <Button v-if="hasPermission('print_prices.create')" label="Tambah Tier" icon="pi pi-plus" size="small" outlined @click="openCreateTier(data.id)" />
                    </div>
                    <DataTable :value="data.tiers" dataKey="id" emptyMessage="Belum ada tier grosir." class="p-datatable-sm">
                        <Column header="Min. Qty (lembar)">
                            <template #body="{ data: tier }">≥ {{ tier.min_qty }} lembar</template>
                        </Column>
                        <Column header="Harga/Lembar">
                            <template #body="{ data: tier }">{{ formatRp(tier.price_per_sheet) }}</template>
                        </Column>
                        <Column header="Aksi" style="width: 8rem" v-if="hasPermission('print_prices.update') || hasPermission('print_prices.delete')">
                            <template #body="{ data: tier }">
                                <div class="flex gap-1">
                                    <Button v-if="hasPermission('print_prices.update')" icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEditTier(tier)" />
                                    <Button v-if="hasPermission('print_prices.delete')" icon="pi pi-trash" severity="danger" text rounded size="small" @click="deleteTier(tier)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
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

        <!-- Tier Dialog -->
        <Dialog v-model:visible="tierDialogVisible"
            :header="tierDialogMode === 'create' ? 'Tambah Tier Grosir' : 'Edit Tier Grosir'"
            modal :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Minimal Qty (lembar)</label>
                    <InputNumber v-model="tierForm.min_qty" :min="1" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Harga per Lembar (Rp)</label>
                    <InputNumber v-model="tierForm.price_per_sheet" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="tierDialogVisible = false" />
                <Button :label="tierDialogMode === 'create' ? 'Simpan' : 'Perbarui'" icon="pi pi-check"
                    :loading="submitting" @click="saveTier" />
            </template>
        </Dialog>
    </div>
</template>
