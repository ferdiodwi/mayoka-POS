<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { apiGet } from '@/composables/useApi';

const toast = useToast();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const selectedProducts = ref([]);
const priceLevel = ref('h1');
const searchQuery = ref('');
const filterCategory = ref(null);
const labelSize = ref('medium'); // small, medium, large
const branchName = ref('');

const priceLevelOptions = [
    { label: 'Harga 1 (H1)', value: 'h1' },
    { label: 'Harga 2 (H2)', value: 'h2' },
    { label: 'Harga 3 (H3)', value: 'h3' },
];

const labelSizeOptions = [
    { label: 'Kecil (5×3 cm)', value: 'small' },
    { label: 'Standar (6×3.5 cm)', value: 'medium' },
    { label: 'Besar (8×5 cm)', value: 'large' },
];

const filteredProducts = computed(() => {
    let result = products.value;
    if (filterCategory.value) {
        result = result.filter(p => p.category_id === filterCategory.value);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.product_code && String(p.product_code).includes(q)) ||
            (p.barcode && p.barcode.includes(q))
        );
    }
    return result;
});

const labelDimensions = computed(() => {
    // 1cm ≈ 37.8px
    switch (labelSize.value) {
        case 'small': return { width: '189px', height: '113px', fontSize: '0.55rem', priceSize: '1.1rem', nameSize: '0.6rem' };
        case 'large': return { width: '302px', height: '189px', fontSize: '0.75rem', priceSize: '1.8rem', nameSize: '0.85rem' };
        default: return { width: '227px', height: '132px', fontSize: '0.6rem', priceSize: '1.3rem', nameSize: '0.68rem' }; // 6cm x 3.5cm
    }
});

onMounted(async () => {
    await fetchData();
});

async function fetchData() {
    loading.value = true;
    try {
        const [prodRes, catRes, branchRes] = await Promise.all([
            apiGet('/api/products/catalog'),
            apiGet('/api/categories'),
            apiGet('/api/branches'),
        ]);
        // Only show 'barang' type products (physical goods with prices)
        products.value = prodRes.products.filter(p => p.type === 'barang');
        categories.value = catRes.categories;

        // Get active branch name
        if (branchRes.branches && branchRes.branches.length > 0) {
            // Use the first (active) branch, or find by activeBranchId
            const activeBranchId = localStorage.getItem('activeBranchId');
            const branch = activeBranchId 
                ? branchRes.branches.find(b => b.id == activeBranchId) || branchRes.branches[0]
                : branchRes.branches[0];
            branchName.value = branch.name || '';
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data produk.', life: 3000 });
    } finally {
        loading.value = false;
    }
}

function toggleProduct(product) {
    const idx = selectedProducts.value.findIndex(p => p.id === product.id);
    if (idx >= 0) {
        selectedProducts.value.splice(idx, 1);
    } else {
        selectedProducts.value.push(product);
    }
}

function isSelected(product) {
    return selectedProducts.value.some(p => p.id === product.id);
}

function selectAll() {
    selectedProducts.value = [...filteredProducts.value];
}

function deselectAll() {
    selectedProducts.value = [];
}

function getUnitPrice(product, unitLevel) {
    if (!product.units) return null;
    const unit = product.units.find(u => u.level === unitLevel);
    if (!unit) return null;
    const priceKey = `price_${priceLevel.value}`;
    return {
        price: parseFloat(unit[priceKey]) || parseFloat(unit.price_h1) || 0,
        unitName: unit.unit_name,
    };
}

function formatPrice(value) {
    return new Intl.NumberFormat('id-ID').format(value);
}

function printLabels() {
    if (selectedProducts.value.length === 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Pilih minimal 1 produk untuk dicetak.', life: 3000 });
        return;
    }
    window.print();
}
</script>

<template>
    <div class="price-label-page">
        <!-- Controls (hidden when printing) -->
        <div class="no-print">
            <div class="card mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold m-0">
                        <i class="pi pi-tag mr-2"></i>Cetak Label Harga
                    </h2>
                    <div class="flex gap-2">
                        <Button label="Cetak Label" icon="pi pi-print" @click="printLabels"
                            :disabled="selectedProducts.length === 0"
                            :badge="String(selectedProducts.length)" badgeSeverity="contrast" />
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-color">CARI PRODUK</label>
                        <InputText v-model="searchQuery" placeholder="Nama / kode / barcode..." class="w-64" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-color">KATEGORI</label>
                        <Select v-model="filterCategory" :options="categories" optionLabel="name" optionValue="id"
                            placeholder="Semua Kategori" showClear class="w-48" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-color">LEVEL HARGA</label>
                        <Select v-model="priceLevel" :options="priceLevelOptions" optionLabel="label" optionValue="value"
                            class="w-44" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-color">UKURAN LABEL</label>
                        <Select v-model="labelSize" :options="labelSizeOptions" optionLabel="label" optionValue="value"
                            class="w-44" />
                    </div>
                    <div class="flex gap-2">
                        <Button label="Pilih Semua" icon="pi pi-check-square" severity="secondary" size="small" outlined @click="selectAll" />
                        <Button label="Batal Semua" icon="pi pi-times" severity="secondary" size="small" outlined @click="deselectAll" />
                    </div>
                </div>
            </div>

            <!-- Product selection grid -->
            <div class="card mb-4">
                <h3 class="text-lg font-semibold mb-3">
                    Pilih Produk
                    <span class="text-sm font-normal text-muted-color ml-2">
                        ({{ selectedProducts.length }} dari {{ filteredProducts.length }} dipilih)
                    </span>
                </h3>
                <div v-if="loading" class="text-center p-8">
                    <i class="pi pi-spin pi-spinner text-4xl text-muted-color"></i>
                </div>
                <div v-else-if="filteredProducts.length === 0" class="text-center p-8 text-muted-color">
                    <i class="pi pi-inbox text-4xl mb-3 block"></i>
                    <p class="m-0">Tidak ada produk ditemukan.</p>
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 max-h-96 overflow-y-auto p-1">
                    <div v-for="product in filteredProducts" :key="product.id"
                        class="border rounded-lg p-3 cursor-pointer transition-all duration-200"
                        :class="isSelected(product) 
                            ? 'border-primary bg-primary/10 ring-2 ring-primary shadow-md' 
                            : 'border-surface-200 dark:border-surface-700 hover:border-primary/50 hover:shadow-sm'"
                        @click="toggleProduct(product)">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="m-0 font-semibold text-sm truncate">{{ product.name }}</p>
                                <p class="m-0 text-xs text-muted-color mt-1">
                                    <span class="font-mono font-bold">{{ product.product_code || '-' }}</span>
                                    <span v-if="product.barcode" class="ml-1">· {{ product.barcode }}</span>
                                </p>
                                <div class="mt-1">
                                    <span v-for="unit in product.units.filter(u => u.level <= 2)" :key="unit.id"
                                        class="text-xs text-muted-color mr-2">
                                        {{ unit.unit_name }}: Rp {{ formatPrice(unit[`price_${priceLevel}`] || unit.price_h1) }}
                                    </span>
                                </div>
                            </div>
                            <i class="pi" :class="isSelected(product) ? 'pi-check-circle text-primary' : 'pi-circle text-surface-300 dark:text-surface-600'" 
                                style="font-size: 1.2rem; flex-shrink: 0;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Label Preview & Print Area -->
        <div class="card no-print-card" v-if="selectedProducts.length > 0">
            <h3 class="text-lg font-semibold mb-3 no-print">
                <i class="pi pi-eye mr-2"></i>Preview Label
                <span class="text-sm font-normal text-muted-color ml-2">({{ selectedProducts.length }} label)</span>
            </h3>
        </div>
        
        <div class="label-print-area" v-if="selectedProducts.length > 0">
            <div class="label-grid">
                <div v-for="product in selectedProducts" :key="product.id"
                    class="price-label"
                    :style="{ width: labelDimensions.width, height: labelDimensions.height }">
                    
                    <!-- Header: MAYOKA + Branch -->
                    <div class="label-header" :style="{ fontSize: labelDimensions.fontSize }">
                        <span class="label-store-name">MAYOKA {{ branchName.toUpperCase() }}</span>
                        <span class="label-code">*{{ product.product_code || '-' }}*</span>
                    </div>

                    <!-- Prices (PCS & PCK only) -->
                    <div class="label-prices">
                        <template v-for="unit in product.units.filter(u => u.level <= 2)" :key="unit.id">
                            <div class="label-price-row"
                                :class="{ 'label-price-primary': unit.level === 1 }">
                                <span class="label-price-value" 
                                    :style="{ fontSize: unit.level === 1 ? labelDimensions.priceSize : `calc(${labelDimensions.priceSize} * 0.6)` }">
                                    Rp {{ formatPrice(unit[`price_${priceLevel}`] || unit.price_h1) }}
                                </span>
                                <span class="label-price-unit" :style="{ fontSize: labelDimensions.fontSize }">
                                    /{{ unit.unit_name }}
                                </span>
                            </div>
                        </template>
                    </div>

                    <!-- Product Name -->
                    <div class="label-product-name" :style="{ fontSize: labelDimensions.nameSize }">
                        {{ product.name.toUpperCase() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="card no-print">
            <div class="text-center p-8 text-muted-color">
                <i class="pi pi-tag text-4xl mb-3 block"></i>
                <p class="m-0 font-semibold">Belum ada produk yang dipilih</p>
                <p class="m-0 text-sm mt-1">Pilih produk dari daftar di atas untuk melihat preview label harga.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ===== Label Styles ===== */
.label-print-area {
    padding: 8px;
}

.label-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: flex-start;
}

.price-label {
    border: 1.5px solid #333;
    background: #fff;
    color: #000;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
    overflow: hidden;
    page-break-inside: avoid;
    break-inside: avoid;
}

.label-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 4px;
    line-height: 1.2;
}

.label-store-name {
    font-weight: 900;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.label-code {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}

.label-prices {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0;
    margin: 2px 0;
}

.label-price-row {
    display: flex;
    align-items: baseline;
    gap: 4px;
    line-height: 1.15;
}

.label-price-primary .label-price-value {
    font-weight: 900;
}

.label-price-value {
    font-weight: 700;
    white-space: nowrap;
}

.label-price-unit {
    font-weight: 600;
    white-space: nowrap;
}

.label-product-name {
    font-weight: 800;
    line-height: 1.2;
    border-top: 1px solid #555;
    padding-top: 3px;
    word-break: break-word;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* ===== Print Styles (scoped) ===== */
@media print {
    .no-print,
    .no-print-card {
        display: none !important;
    }

    .price-label-page {
        margin: 0 !important;
        padding: 0 !important;
    }

    .label-print-area {
        padding: 0 !important;
        margin: 0 !important;
    }

    .label-grid {
        display: block !important;
        font-size: 0; /* remove inline-block whitespace */
    }

    .price-label {
        display: inline-flex !important;
        vertical-align: top;
        border: 1px solid #000 !important;
        margin: 2px;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<!-- Global print styles (unscoped) to hide layout elements -->
<style>
@media print {
    /* Hide layout: topbar, sidebar, breadcrumb, config panel */
    .layout-topbar,
    .layout-sidebar,
    .layout-menu,
    .layout-breadcrumb,
    .layout-config-button,
    .layout-config,
    .p-toast,
    .p-confirmdialog,
    nav,
    header,
    .topbar-menu {
        display: none !important;
    }

    /* Make content area full width without padding */
    .layout-wrapper,
    .layout-main,
    .layout-main-container,
    .layout-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-height: auto !important;
    }

    /* Remove card styling in print */
    .card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    body, html {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    @page {
        size: A4;
        margin: 10mm;
    }
}
</style>
