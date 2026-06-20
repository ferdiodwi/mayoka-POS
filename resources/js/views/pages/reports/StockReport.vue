<script setup>
import { ref, onMounted } from 'vue';
import { apiGet } from '@/composables/useApi';

const loading = ref(false);
const products = ref([]);
const categories = ref([]);
const filterCategory = ref(null);
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const dateTo = ref(new Date());

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function toApiDate(d) {
    const dt = new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

async function fetchCategories() {
    const data = await apiGet('/api/categories');
    categories.value = data.categories;
}

async function fetchReport() {
    loading.value = true;
    try {
        let url = `/api/reports/stock?date_from=${toApiDate(dateFrom.value)}&date_to=${toApiDate(dateTo.value)}`;
        if (filterCategory.value) url += `&category_id=${filterCategory.value}`;
        const data = await apiGet(url);
        products.value = data.products;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await fetchCategories();
    await fetchReport();
});
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold m-0">Rekap Stok</h2>
        </div>

        <div class="flex items-end gap-4 mb-6">
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
                <Select v-model="filterCategory" :options="categories" optionLabel="name" optionValue="id"
                    placeholder="Semua Kategori" showClear class="w-48" />
            </div>
            <Button label="Tampilkan" icon="pi pi-search" @click="fetchReport" />
        </div>

        <DataTable :value="products" :loading="loading" stripedRows paginator :rows="20" dataKey="id"
            emptyMessage="Tidak ada data produk." :rowClass="(data) => data.is_low ? 'bg-red-50 dark:bg-red-900/10' : ''">
            <Column field="name" header="Produk" sortable />
            <Column field="category" header="Kategori" sortable style="width: 7rem" />
            <Column header="Stok Saat Ini" sortable sortField="stock_current" style="width: 7rem">
                <template #body="{ data }">
                    <span :class="{ 'text-red-500 font-bold': data.is_low }">
                        {{ data.stock_current }} {{ data.unit }}
                    </span>
                    <i v-if="data.is_low" class="pi pi-exclamation-triangle text-red-500 ml-1"></i>
                </template>
            </Column>
            <Column field="min_stock" header="Min Stok" style="width: 5rem" />
            <Column header="Masuk" style="width: 5rem">
                <template #body="{ data }">
                    <span v-if="data.stock_in > 0" class="text-green-600 dark:text-green-400">+{{ data.stock_in }}</span>
                    <span v-else class="text-muted-color">0</span>
                </template>
            </Column>
            <Column header="Keluar" style="width: 5rem">
                <template #body="{ data }">
                    <span v-if="data.stock_out > 0" class="text-red-500">-{{ data.stock_out }}</span>
                    <span v-else class="text-muted-color">0</span>
                </template>
            </Column>
            <Column header="Adj." style="width: 5rem">
                <template #body="{ data }">
                    <span v-if="data.adjustment !== 0" :class="data.adjustment > 0 ? 'text-blue-500' : 'text-orange-500'">
                        {{ data.adjustment > 0 ? '+' : '' }}{{ data.adjustment }}
                    </span>
                    <span v-else class="text-muted-color">0</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
