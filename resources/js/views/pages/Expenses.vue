<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();
const confirm = useConfirm();

const expenses = ref([]);
const summary = ref({ total: 0, by_category: {} });
const categoryLabels = ref({});
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create');
const submitting = ref(false);
const editingId = ref(null);

const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());
const filterCategory = ref(null);

const currentPage = ref(1);
const totalRecords = ref(0);

const categoryOptions = [
    { label: 'Listrik', value: 'listrik' },
    { label: 'Sewa Ruko', value: 'sewa' },
    { label: 'Gaji Karyawan', value: 'gaji' },
    { label: 'Operasional', value: 'operasional' },
    { label: 'Bahan Baku', value: 'bahan_baku' },
    { label: 'Lainnya', value: 'lainnya' },
];

const form = ref({
    expense_date: new Date(),
    category: 'operasional',
    amount: 0,
    description: '',
    notes: '',
});

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

function getCategoryLabel(val) {
    return categoryLabels.value[val] || val;
}

function getCategorySeverity(val) {
    const map = { listrik: 'warn', sewa: 'info', gaji: 'success', operasional: 'secondary', bahan_baku: 'danger', lainnya: 'contrast' };
    return map[val] || 'info';
}

async function fetchExpenses() {
    loading.value = true;
    try {
        let url = `/api/expenses?page=${currentPage.value}&date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`;
        if (filterCategory.value) url += `&category=${filterCategory.value}`;
        const data = await apiGet(url);
        expenses.value = data.expenses.data;
        totalRecords.value = data.expenses.total;
        summary.value = data.summary;
        categoryLabels.value = data.category_labels;
    } finally {
        loading.value = false;
    }
}

function onPageChange(event) {
    currentPage.value = event.page + 1;
    fetchExpenses();
}

function openCreate() {
    dialogMode.value = 'create';
    form.value = { expense_date: new Date(), category: 'operasional', amount: 0, description: '', notes: '' };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    form.value = {
        expense_date: new Date(item.expense_date),
        category: item.category,
        amount: parseFloat(item.amount),
        description: item.description,
        notes: item.notes || '',
    };
    editingId.value = item.id;
    dialogVisible.value = true;
}

async function save() {
    if (!form.value.description) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Deskripsi wajib diisi.', life: 3000 });
        return;
    }
    submitting.value = true;
    try {
        const payload = {
            ...form.value,
            expense_date: toApiDate(form.value.expense_date),
        };
        if (dialogMode.value === 'edit') {
            await apiPut(`/api/expenses/${editingId.value}`, payload);
        } else {
            await apiPost('/api/expenses', payload);
        }
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pengeluaran disimpan.', life: 3000 });
        dialogVisible.value = false;
        await fetchExpenses();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(item) {
    confirm.require({
        message: `Hapus pengeluaran "${item.description}"?`,
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Hapus',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await apiDelete(`/api/expenses/${item.id}`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pengeluaran dihapus.', life: 3000 });
                await fetchExpenses();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

onMounted(fetchExpenses);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Pengeluaran Operasional</h2>
            <Button label="Tambah Pengeluaran" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4 mb-6">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Dari Tanggal</label>
                <DatePicker v-model="dateFrom" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Sampai Tanggal</label>
                <DatePicker v-model="dateTo" dateFormat="dd/mm/yy" showIcon />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Kategori</label>
                <Select v-model="filterCategory" :options="categoryOptions" optionLabel="label" optionValue="value"
                    placeholder="Semua" showClear class="w-40" />
            </div>
            <Button label="Tampilkan" icon="pi pi-search" @click="fetchExpenses" />
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" v-if="summary.total > 0">
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center col-span-2 md:col-span-1">
                <p class="text-sm text-muted-color m-0">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400 m-0 mt-1">{{ formatRp(summary.total) }}</p>
            </div>
            <div v-for="(amount, cat) in summary.by_category" :key="cat"
                class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg text-center">
                <p class="text-xs text-muted-color m-0">{{ getCategoryLabel(cat) }}</p>
                <p class="text-lg font-bold m-0 mt-1">{{ formatRp(amount) }}</p>
            </div>
        </div>

        <DataTable :value="expenses" :loading="loading" stripedRows lazy paginator
            :rows="20" :totalRecords="totalRecords" :first="(currentPage - 1) * 20"
            @page="onPageChange" dataKey="id" emptyMessage="Belum ada data pengeluaran.">
            <Column header="Tanggal" style="width: 7rem">
                <template #body="{ data }">{{ formatDate(data.expense_date) }}</template>
            </Column>
            <Column header="Kategori" style="width: 7rem">
                <template #body="{ data }">
                    <Tag :value="getCategoryLabel(data.category)" :severity="getCategorySeverity(data.category)" />
                </template>
            </Column>
            <Column field="description" header="Deskripsi" />
            <Column header="Jumlah" style="width: 9rem">
                <template #body="{ data }">
                    <span class="font-semibold text-red-600 dark:text-red-400">{{ formatRp(data.amount) }}</span>
                </template>
            </Column>
            <Column header="Dicatat" style="width: 6rem">
                <template #body="{ data }">{{ data.user?.name }}</template>
            </Column>
            <Column header="Aksi" style="width: 6rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" severity="info" text rounded size="small" @click="openEdit(data)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Pengeluaran' : 'Edit Pengeluaran'"
            modal :style="{ width: '500px' }" :breakpoints="{ '768px': '95vw' }">
            <div class="flex flex-col gap-4 pt-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Tanggal</label>
                        <DatePicker v-model="form.expense_date" dateFormat="dd/mm/yy" showIcon />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold">Kategori</label>
                        <Select v-model="form.category" :options="categoryOptions" optionLabel="label" optionValue="value" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Jumlah (Rp)</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Deskripsi</label>
                    <InputText v-model="form.description" placeholder="Contoh: Bayar listrik bulan Juni" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Catatan (Opsional)</label>
                    <InputText v-model="form.notes" placeholder="Keterangan tambahan" />
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button :label="dialogMode === 'create' ? 'Simpan' : 'Perbarui'" icon="pi pi-check"
                    :loading="submitting" @click="save" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
