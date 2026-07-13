<script setup>
import { ref, onMounted } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();
const confirm = useConfirm();

const branches = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('add');
const submitted = ref(false);
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const branch = ref({
    id: null,
    name: '',
    tagline: '',
    address: '',
    phone: '',
    receipt_footer: '',
    is_active: true
});

onMounted(() => {
    fetchBranches();
});

async function fetchBranches() {
    loading.value = true;
    try {
        const data = await apiGet('/api/branches');
        branches.value = data.branches;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 3000 });
    } finally {
        loading.value = false;
    }
}

function openNew() {
    branch.value = {
        id: null,
        name: '',
        tagline: '',
        address: '',
        phone: '',
        receipt_footer: '',
        is_active: true
    };
    submitted.value = false;
    dialogMode.value = 'add';
    dialogVisible.value = true;
}

function editBranch(b) {
    branch.value = { ...b };
    branch.value.is_active = b.is_active == 1; // force boolean
    submitted.value = false;
    dialogMode.value = 'edit';
    dialogVisible.value = true;
}

function hideDialog() {
    dialogVisible.value = false;
    submitted.value = false;
}

async function saveBranch() {
    submitted.value = true;

    if (!branch.value.name?.trim()) return;

    const payload = { ...branch.value };

    try {
        if (dialogMode.value === 'add') {
            await apiPost('/api/branches', payload);
            toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Cabang ditambahkan', life: 3000 });
        } else {
            await apiPut(`/api/branches/${branch.value.id}`, payload);
            toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Cabang diperbarui', life: 3000 });
        }
        dialogVisible.value = false;
        fetchBranches();
        
        // Note: Admin might need to refresh page if active branch changed, 
        // but let's keep it simple. The topbar fetchBranches will update on full reload anyway.
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 3000 });
    }
}

function confirmDelete(b) {
    if (b.id === 1) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Cabang Pusat tidak dapat dihapus', life: 3000 });
        return;
    }

    confirm.require({
        message: `Hapus cabang ${b.name}? Data operasional terkait tidak akan ikut terhapus.`,
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await apiDelete(`/api/branches/${b.id}`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Cabang dihapus', life: 3000 });
                fetchBranches();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 3000 });
            }
        }
    });
}
</script>

<template>
    <div class="card">
        <div class="font-semibold text-xl mb-4">Manajemen Cabang</div>

        <DataTable :value="branches" :paginator="true" :rows="10" dataKey="id"
            :filters="filters" filterDisplay="menu" :loading="loading"
            globalFilterFields="['name', 'address', 'phone']"
            emptyMessage="Tidak ada data cabang."
            responsiveLayout="scroll">
            <template #header>
                <div class="flex justify-between items-center flex-col sm:flex-row gap-4">
                    <Button label="Tambah Cabang" icon="pi pi-plus" class="p-button-success w-full sm:w-auto" @click="openNew" />
                    <IconField iconPosition="left" class="w-full sm:w-auto">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filters['global'].value" placeholder="Cari cabang..." class="w-full" />
                    </IconField>
                </div>
            </template>
            <Column field="name" header="Nama Cabang" sortable></Column>
            <Column field="tagline" header="Tagline / Slogan" sortable></Column>
            <Column field="address" header="Alamat" sortable></Column>
            <Column field="phone" header="Telepon" sortable></Column>
            <Column field="is_active" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :severity="data.is_active ? 'success' : 'danger'" :value="data.is_active ? 'Aktif' : 'Nonaktif'" />
                </template>
            </Column>
            <Column headerStyle="width: 10rem">
                <template #body="slotProps">
                    <div class="flex gap-2">
                        <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editBranch(slotProps.data)" />
                        <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDelete(slotProps.data)" :disabled="slotProps.data.id === 1" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" :style="{ width: '450px' }" header="Detail Cabang" :modal="true" class="p-fluid">
            <div class="flex flex-col gap-2 mb-4 mt-2">
                <label for="name" class="font-semibold">Nama Cabang</label>
                <InputText id="name" v-model.trim="branch.name" required="true" autofocus :invalid="submitted && !branch.name" />
                <small class="p-error" v-if="submitted && !branch.name">Nama cabang wajib diisi.</small>
            </div>
            
            <div class="flex flex-col gap-2 mb-4">
                <label for="tagline" class="font-semibold">Slogan / Tagline (Opsional)</label>
                <InputText id="tagline" v-model="branch.tagline" placeholder="Contoh: TOKO ALAT TULIS KANTOR" />
            </div>
            
            <div class="flex flex-col gap-2 mb-4">
                <label for="address" class="font-semibold">Alamat (Opsional)</label>
                <Textarea id="address" v-model="branch.address" rows="3" />
            </div>

            <div class="flex flex-col gap-2 mb-4">
                <label for="phone" class="font-semibold">No. Telepon (Opsional)</label>
                <InputText id="phone" v-model="branch.phone" />
            </div>

            <div class="flex flex-col gap-2 mb-4">
                <label for="receipt_footer" class="font-semibold">Pesan Bawah Struk (Opsional)</label>
                <InputText id="receipt_footer" v-model="branch.receipt_footer" placeholder="Contoh: Terima Kasih Atas Kunjungan Anda" />
            </div>

            <div class="flex items-center gap-2 mb-4 mt-4">
                <Checkbox id="is_active" v-model="branch.is_active" :binary="true" />
                <label for="is_active" class="font-semibold mb-0">Cabang Aktif</label>
            </div>

            <template #footer>
                <Button label="Batal" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Simpan" icon="pi pi-check" @click="saveBranch" />
            </template>
        </Dialog>
    </div>
</template>
