<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();
const confirm = useConfirm();

const categories = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create');
const form = ref({ name: '' });
const editingId = ref(null);
const submitting = ref(false);

async function fetchData() {
    loading.value = true;
    try {
        const data = await apiGet('/api/categories');
        categories.value = data.categories;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    dialogMode.value = 'create';
    form.value = { name: '' };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    form.value = { name: item.name };
    editingId.value = item.id;
    dialogVisible.value = true;
}

async function save() {
    submitting.value = true;
    try {
        const isEdit = dialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/categories/${editingId.value}`, form.value)
            : await apiPost('/api/categories', form.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        dialogVisible.value = false;
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(item) {
    confirm.require({
        message: `Hapus kategori "${item.name}"?`,
        header: 'Konfirmasi Hapus',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Hapus',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                const data = await apiDelete(`/api/categories/${item.id}`);
                toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
                await fetchData();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            }
        },
    });
}

onMounted(fetchData);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Kategori Produk</h2>
            <Button label="Tambah Kategori" icon="pi pi-plus" @click="openCreate" />
        </div>

        <DataTable :value="categories" :loading="loading" stripedRows dataKey="id"
            emptyMessage="Belum ada data kategori.">
            <Column header="No" style="width: 4rem">
                <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column field="name" header="Nama Kategori" sortable />
            <Column field="products_count" header="Jumlah Produk" sortable style="width: 10rem" />
            <Column header="Aksi" style="width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-pencil" severity="info" text rounded @click="openEdit(data)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Kategori' : 'Edit Kategori'"
            modal :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="flex flex-col gap-2">
                    <label for="cat-name" class="font-semibold">Nama Kategori</label>
                    <InputText id="cat-name" v-model="form.name" placeholder="Masukkan nama kategori"
                        @keyup.enter="save" />
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
