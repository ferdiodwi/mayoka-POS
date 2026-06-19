<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const toast = useToast();
const confirm = useConfirm();

const users = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create'); // 'create' or 'edit'
const form = ref({ name: '', username: '', password: '', role: 'kasir', is_active: true });
const editingUserId = ref(null);
const submitting = ref(false);

const roleOptions = [
    { label: 'Kasir', value: 'kasir' },
    { label: 'Owner', value: 'owner' },
];

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchUsers() {
    loading.value = true;
    try {
        const res = await fetch('/api/users', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            users.value = data.users;
        }
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    dialogMode.value = 'create';
    form.value = { name: '', username: '', password: '', role: 'kasir', is_active: true };
    editingUserId.value = null;
    dialogVisible.value = true;
}

function openEditDialog(user) {
    dialogMode.value = 'edit';
    form.value = {
        name: user.name,
        username: user.username,
        password: '',
        role: user.role,
        is_active: user.is_active,
    };
    editingUserId.value = user.id;
    dialogVisible.value = true;
}

async function saveUser() {
    submitting.value = true;
    try {
        const isEdit = dialogMode.value === 'edit';
        const url = isEdit ? `/api/users/${editingUserId.value}` : '/api/users';
        const method = isEdit ? 'PUT' : 'POST';

        const body = { ...form.value };
        if (isEdit && !body.password) {
            delete body.password;
        }

        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await res.json();

        if (!res.ok) {
            const errors = data.errors ? Object.values(data.errors).flat().join(', ') : data.message;
            throw new Error(errors);
        }

        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        dialogVisible.value = false;
        await fetchUsers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

function confirmDeactivate(user) {
    confirm.require({
        message: `Apakah Anda yakin ingin menonaktifkan user "${user.name}"?`,
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Nonaktifkan',
        acceptClass: 'p-button-danger',
        accept: () => deactivateUser(user.id),
    });
}

async function deactivateUser(userId) {
    try {
        const res = await fetch(`/api/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await res.json();

        if (!res.ok) throw new Error(data.message);

        toast.add({ severity: 'success', summary: 'Berhasil', detail: data.message, life: 3000 });
        await fetchUsers();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

onMounted(fetchUsers);
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Manajemen User</h2>
            <Button label="Tambah User" icon="pi pi-plus" @click="openCreateDialog" />
        </div>

        <DataTable :value="users" :loading="loading" stripedRows paginator :rows="10" dataKey="id"
            emptyMessage="Belum ada data user.">
            <Column field="name" header="Nama" sortable />
            <Column field="username" header="Username" sortable />
            <Column field="role" header="Role" sortable>
                <template #body="{ data }">
                    <Tag :value="data.role === 'owner' ? 'Owner' : 'Kasir'"
                        :severity="data.role === 'owner' ? 'info' : 'success'" />
                </template>
            </Column>
            <Column field="is_active" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Aksi" style="width: 12rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-pencil" severity="info" text rounded @click="openEditDialog(data)" />
                        <Button v-if="data.is_active" icon="pi pi-ban" severity="danger" text rounded
                            @click="confirmDeactivate(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Tambah User Baru' : 'Edit User'"
            modal :style="{ width: '500px' }">
            <div class="flex flex-col gap-4 pt-4">
                <div class="flex flex-col gap-2">
                    <label for="name" class="font-semibold">Nama Lengkap</label>
                    <InputText id="name" v-model="form.name" placeholder="Masukkan nama lengkap" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="form-username" class="font-semibold">Username</label>
                    <InputText id="form-username" v-model="form.username" placeholder="Masukkan username" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="form-password" class="font-semibold">
                        Password
                        <span v-if="dialogMode === 'edit'" class="text-muted-color font-normal text-sm">
                            (kosongkan jika tidak diubah)
                        </span>
                    </label>
                    <Password id="form-password" v-model="form.password" placeholder="Masukkan password"
                        :toggleMask="true" :feedback="false" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="form-role" class="font-semibold">Role</label>
                    <Select id="form-role" v-model="form.role" :options="roleOptions" optionLabel="label"
                        optionValue="value" placeholder="Pilih role" />
                </div>
                <div v-if="dialogMode === 'edit'" class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" />
                    <label class="font-semibold">{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</label>
                </div>
            </div>

            <template #footer>
                <Button label="Batal" icon="pi pi-times" severity="secondary" text @click="dialogVisible = false" />
                <Button :label="dialogMode === 'create' ? 'Simpan' : 'Perbarui'" icon="pi pi-check"
                    :loading="submitting" @click="saveUser" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
