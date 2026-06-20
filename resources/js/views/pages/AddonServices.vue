<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { apiGet, apiPost, apiPut, apiDelete } from '@/composables/useApi';

const toast = useToast();

const addons = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create');
const form = ref({ name: '', price: 0 });
const editingId = ref(null);
const submitting = ref(false);

function formatRp(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }

async function fetchData() {
    loading.value = true;
    try {
        const data = await apiGet('/api/addon-services');
        addons.value = data.addon_services;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    dialogMode.value = 'create';
    form.value = { name: '', price: 0 };
    editingId.value = null;
    dialogVisible.value = true;
}

function openEdit(item) {
    dialogMode.value = 'edit';
    form.value = { name: item.name, price: parseFloat(item.price), is_active: item.is_active };
    editingId.value = item.id;
    dialogVisible.value = true;
}

async function save() {
    submitting.value = true;
    try {
        const isEdit = dialogMode.value === 'edit';
        const data = isEdit
            ? await apiPut(`/api/addon-services/${editingId.value}`, form.value)
            : await apiPost('/api/addon-services', form.value);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        dialogVisible.value = false;
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function deactivate(item) {
    try {
        const data = await apiDelete(`/api/addon-services/${item.id}`);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        await fetchData();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

onMounted(fetchData);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Jasa Tambahan (Addon)</h2>
            <Button label="Tambah Jasa" icon="pi pi-plus" @click="openCreate" />
        </div>

        <DataTable :value="addons" :loading="loading" stripedRows dataKey="id"
            emptyMessage="Belum ada jasa tambahan.">
            <Column header="No" style="width: 4rem">
                <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column field="name" header="Nama Jasa" sortable />
            <Column header="Harga" sortable sortField="price">
                <template #body="{ data }">{{ formatRp(data.price) }}</template>
            </Column>
            <Column header="Status" style="width: 6rem">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-pencil" severity="info" text rounded @click="openEdit(data)" />
                        <Button v-if="data.is_active" icon="pi pi-ban" severity="danger" text rounded
                            @click="deactivate(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah Jasa Tambahan' : 'Edit Jasa Tambahan'"
            modal :style="{ width: '420px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Nama Jasa</label>
                    <InputText v-model="form.name" placeholder="Misal: Jilid Lakban" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Harga (Rp)</label>
                    <InputNumber v-model="form.price" mode="currency" currency="IDR" locale="id-ID" :min="0" />
                </div>
                <div v-if="dialogMode === 'edit'" class="flex items-center gap-2">
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
    </div>
</template>
