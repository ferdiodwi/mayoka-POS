<script setup>
import { ref, onMounted } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();
const customers = ref([]);
const loading = ref(true);
const customerDialog = ref(false);
const deleteDialog = ref(false);
const customer = ref({});
const submitted = ref(false);
const isEdit = ref(false);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const typeOptions = [
    { label: 'Umum', value: 'umum' },
    { label: 'Member', value: 'member' }
];

const priceOptions = [
    { label: 'H1 (Ecer)', value: 'h1' },
    { label: 'H2 (Grosir)', value: 'h2' },
    { label: 'H3 (Khusus)', value: 'h3' }
];

async function loadCustomers() {
    loading.value = true;
    try {
        // Fetch without pagination for table (or modify backend to return all for simplicity)
        const res = await apiGet('/api/customers');
        // Backend returns paginated, so get the data part
        customers.value = res.data || [];
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data pelanggan', life: 3000 });
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadCustomers();
});

function openNew() {
    customer.value = { type: 'umum', price_level: 'h1', is_active: true };
    submitted.value = false;
    isEdit.value = false;
    customerDialog.value = true;
}

function editCustomer(data) {
    customer.value = { ...data };
    submitted.value = false;
    isEdit.value = true;
    customerDialog.value = true;
}

function confirmDelete(data) {
    customer.value = data;
    deleteDialog.value = true;
}

function hideDialog() {
    customerDialog.value = false;
    submitted.value = false;
}

async function saveCustomer() {
    submitted.value = true;
    if (!customer.value.name) return;

    try {
        if (isEdit.value) {
            await apiPut(`/api/customers/${customer.value.id}`, customer.value);
            toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pelanggan berhasil diperbarui', life: 3000 });
        } else {
            await apiPost('/api/customers', customer.value);
            toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pelanggan berhasil ditambahkan', life: 3000 });
        }
        hideDialog();
        loadCustomers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.message || 'Gagal menyimpan data', life: 5000 });
    }
}

async function deleteCustomer() {
    try {
        await apiDelete(`/api/customers/${customer.value.id}`);
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pelanggan dihapus', life: 3000 });
        deleteDialog.value = false;
        customer.value = {};
        loadCustomers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.message || 'Gagal menghapus data', life: 5000 });
        deleteDialog.value = false;
    }
}
</script>

<template>
    <div class="card">
        <Toast />
        <Toolbar class="mb-4">
            <template #start>
                        <Button label="Tambah" icon="pi pi-plus" severity="success" class="mr-2" @click="openNew" />
                    </template>
                </Toolbar>

                <DataTable ref="dt" :value="customers" :loading="loading" dataKey="id"
                    :paginator="true" :rows="10" :filters="filters"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    :rowsPerPageOptions="[5, 10, 25]"
                    currentPageReportTemplate="Menampilkan {first} sampai {last} dari {totalRecords} pelanggan">
                    <template #header>
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                            <h5 class="m-0">Master Data Pelanggan (Customer / Member)</h5>
                            <IconField iconPosition="left" class="block mt-2 md:mt-0">
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="filters['global'].value" placeholder="Cari..." />
                            </IconField>
                        </div>
                    </template>

                    <Column field="code" header="Kode" :sortable="true" style="width: 15%"></Column>
                    <Column field="name" header="Nama Pelanggan" :sortable="true" style="width: 25%"></Column>
                    <Column field="type" header="Tipe" :sortable="true" style="width: 15%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.type" :severity="slotProps.data.type === 'member' ? 'info' : 'secondary'" />
                        </template>
                    </Column>
                    <Column field="price_level" header="Harga" :sortable="true" style="width: 15%">
                        <template #body="slotProps">
                            <span class="uppercase font-bold">{{ slotProps.data.price_level }}</span>
                        </template>
                    </Column>
                    <Column field="phone" header="Telepon" style="width: 15%"></Column>
                    <Column header="Aksi" style="width: 15%">
                        <template #body="slotProps">
                            <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editCustomer(slotProps.data)" />
                            <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDelete(slotProps.data)" 
                                :disabled="slotProps.data.code === '10001'" />
                        </template>
                    </Column>
                </DataTable>

                <!-- Dialog Create/Edit -->
                <Dialog v-model:visible="customerDialog" :style="{ width: '450px' }" header="Detail Pelanggan" :modal="true" class="p-fluid">
                    <div class="flex flex-col gap-2 mb-4 mt-2">
                        <label for="name" class="font-semibold">Nama Pelanggan</label>
                        <InputText id="name" v-model.trim="customer.name" required="true" autofocus :invalid="submitted && !customer.name" />
                        <small class="p-error" v-if="submitted && !customer.name">Nama harus diisi.</small>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col gap-2">
                            <label for="type" class="font-semibold">Tipe</label>
                            <Dropdown id="type" v-model="customer.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Pilih Tipe" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="price_level" class="font-semibold">Level Harga</label>
                            <Dropdown id="price_level" v-model="customer.price_level" :options="priceOptions" optionLabel="label" optionValue="value" placeholder="Pilih Harga" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 mb-4">
                        <label for="phone" class="font-semibold">Telepon</label>
                        <InputText id="phone" v-model.trim="customer.phone" />
                    </div>

                    <div class="flex flex-col gap-2 mb-4">
                        <label for="address" class="font-semibold">Alamat</label>
                        <Textarea id="address" v-model="customer.address" required="true" rows="3" cols="20" />
                    </div>

                    <template #footer>
                        <Button label="Batal" icon="pi pi-times" text @click="hideDialog" />
                        <Button label="Simpan" icon="pi pi-check" text @click="saveCustomer" />
                    </template>
                </Dialog>

                <!-- Dialog Delete -->
                <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Konfirmasi" :modal="true">
                    <div class="flex items-center justify-center">
                        <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                        <span v-if="customer">Apakah Anda yakin ingin menghapus pelanggan <b>{{ customer.name }}</b>?</span>
                    </div>
                    <template #footer>
                        <Button label="Tidak" icon="pi pi-times" text @click="deleteDialog = false" />
                        <Button label="Ya" icon="pi pi-check" text @click="deleteCustomer" />
                    </template>
                </Dialog>
    </div>
</template>
