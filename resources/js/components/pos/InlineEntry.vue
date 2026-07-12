<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { usePosData } from '@/composables/usePosData';
import { useCart } from '@/composables/useCart';
import { useToast } from 'primevue/usetoast';
import { formatRp } from '@/utils/format';

const props = defineProps({
    priceLevel: { type: String, default: 'h1' },
});

const emit = defineEmits(['item-added']);
const toast = useToast();
const { searchProducts, updateProductStock } = usePosData();
const { addProductItem } = useCart();

// --- Refs ---
const searchRef = ref(null);
const unitRef = ref(null);
const qtyRef = ref(null);
const discRef = ref(null);

// --- State ---
const searchQuery = ref('');
const selectedProduct = ref(null);
const selectedUnitIndex = ref(0);
const price = ref(0);
const qty = ref(1);
const discount = ref(0);
const lookupResults = ref([]);
const lookupIndex = ref(0);
const lookupVisible = ref(false);
const entryPhase = ref('search'); // 'search' | 'unit' | 'qty' | 'disc'

// --- Computed ---
const unitOptions = computed(() => {
    if (!selectedProduct.value || !selectedProduct.value.units) return [];
    return selectedProduct.value.units.map((u, i) => ({
        label: u.unit_name,
        value: i,
        unit: u,
    }));
});

const selectedUnit = computed(() => {
    const opts = unitOptions.value;
    if (opts.length === 0) return null;
    return opts[selectedUnitIndex.value]?.unit || opts[0]?.unit;
});

const stockDisplay = computed(() => {
    if (!selectedProduct.value) return '';
    const p = selectedProduct.value;
    if (p.type !== 'barang') return '';
    
    const units = p.units || [];
    if (units.length === 0) return `${p.stock}`;
    
    const sorted = [...units].sort((a, b) => b.base_multiplier - a.base_multiplier);
    let remaining = p.stock;
    let parts = [];
    for (const u of sorted) {
        const q = Math.floor(remaining / u.base_multiplier);
        if (q > 0) {
            parts.push(`${q} ${u.unit_name}`);
            remaining %= u.base_multiplier;
        }
    }
    return parts.length > 0 ? parts.join(' ') : `0 ${sorted[sorted.length-1]?.unit_name || ''}`;
});

// --- Watchers ---
watch(searchQuery, (val) => {
    if (!val || val.length < 1) {
        lookupResults.value = [];
        lookupVisible.value = false;
        return;
    }
    if (entryPhase.value === 'search') {
        lookupResults.value = searchProducts(val);
        lookupVisible.value = lookupResults.value.length > 0;
        lookupIndex.value = 0;
    }
});

watch(selectedUnitIndex, () => {
    updatePrice();
});

watch(() => props.priceLevel, () => {
    updatePrice();
});

// --- Methods ---
function updatePrice() {
    const unit = selectedUnit.value;
    if (!unit) { price.value = 0; return; }
    const key = `price_${props.priceLevel}`;
    price.value = parseFloat(unit[key]) || parseFloat(unit.price_h1) || 0;
}

function focusSearch() {
    resetEntry();
    nextTick(() => {
        const el = searchRef.value?.$el || searchRef.value;
        const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
        if (input) { input.focus(); input.select(); }
    });
}

function resetEntry() {
    searchQuery.value = '';
    selectedProduct.value = null;
    selectedUnitIndex.value = 0;
    price.value = 0;
    qty.value = 1;
    discount.value = 0;
    lookupResults.value = [];
    lookupVisible.value = false;
    lookupIndex.value = 0;
    entryPhase.value = 'search';
}

function selectProduct(product) {
    selectedProduct.value = product;
    searchQuery.value = product.name;
    lookupVisible.value = false;
    
    // Set default unit (level 1, smallest)
    selectedUnitIndex.value = 0;
    updatePrice();
    
    // Move to unit selection
    entryPhase.value = 'unit';
    nextTick(() => {
        const el = unitRef.value?.$el || unitRef.value;
        const input = el?.tagName === 'SELECT' ? el : el?.querySelector('select') || el?.querySelector('[role="combobox"]') || el?.querySelector('input');
        if (input) input.focus();
    });
}

function confirmUnit() {
    entryPhase.value = 'qty';
    nextTick(() => {
        const el = qtyRef.value?.$el || qtyRef.value;
        const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
        if (input) { input.focus(); input.select(); }
    });
}

function confirmQty() {
    entryPhase.value = 'disc';
    nextTick(() => {
        const el = discRef.value?.$el || discRef.value;
        const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
        if (input) { input.focus(); input.select(); }
    });
}

function confirmDisc() {
    if (!selectedProduct.value || !selectedUnit.value || qty.value < 1) return;
    
    // Check stock
    const p = selectedProduct.value;
    const u = selectedUnit.value;
    if (p.type === 'barang') {
        const stockNeeded = qty.value * u.base_multiplier;
        if (p.stock < stockNeeded) {
            toast.add({ severity: 'warn', summary: 'Stok Kurang', detail: `${p.name} stok: ${stockDisplay.value}`, life: 3000 });
            return;
        }
    }

    // Add to cart
    addProductItem({
        id: p.id,
        name: p.name,
        barcode: p.barcode,
        type: p.type,
        stock: p.stock,
        cost_price: p.cost_price,
        units: p.units,
    }, qty.value, u, props.priceLevel, price.value, discount.value);

    toast.add({ severity: 'success', summary: 'Ditambahkan', detail: `${p.name} x${qty.value}`, life: 1500 });
    emit('item-added');
    
    // Reset and refocus
    focusSearch();
}

// --- Keyboard handlers ---
function handleSearchKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (lookupVisible.value) {
            lookupIndex.value = Math.min(lookupIndex.value + 1, lookupResults.value.length - 1);
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (lookupVisible.value) {
            lookupIndex.value = Math.max(lookupIndex.value - 1, 0);
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (lookupVisible.value && lookupResults.value.length > 0) {
            selectProduct(lookupResults.value[lookupIndex.value]);
        } else if (!searchQuery.value && entryPhase.value === 'search') {
            // Empty search + Enter = go to payment
            emit('go-to-payment');
        }
    } else if (e.key === 'Escape') {
        lookupVisible.value = false;
    }
}

function handleUnitKeydown(e) {
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedUnitIndex.value = Math.max(0, selectedUnitIndex.value - 1);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedUnitIndex.value = Math.min(unitOptions.value.length - 1, selectedUnitIndex.value + 1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        confirmUnit();
    }
}

function handleQtyKeydown(e) {
    if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        confirmQty();
    }
}

function handleDiscKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmDisc();
    }
}

defineExpose({ focusSearch });
</script>

<template>
    <div class="flex flex-col gap-2">
        <!-- Stock info bar -->
        <div v-if="selectedProduct && selectedProduct.type === 'barang'"
            class="text-sm font-semibold px-2 py-1 rounded"
            :class="selectedProduct.stock > 0 ? 'text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20' : 'text-red-500 bg-red-50 dark:bg-red-900/20'">
            STOK: {{ stockDisplay }} ({{ selectedProduct.stock }} base)
        </div>

        <!-- Input row -->
        <div class="flex gap-2 items-end">
            <!-- Search / Barcode -->
            <div class="flex-1 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">KODE / NAMA</label>
                <InputText ref="searchRef" v-model="searchQuery"
                    placeholder="Scan barcode atau ketik nama..."
                    class="w-full font-mono h-10"
                    :class="entryPhase === 'search' ? 'ring-2 ring-primary' : ''"
                    @keydown="handleSearchKeydown"
                    @focus="entryPhase = 'search'" />
            </div>

            <!-- Unit dropdown -->
            <div class="w-28 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">SATUAN</label>
                <select ref="unitRef"
                    :value="selectedUnitIndex"
                    @change="selectedUnitIndex = Number($event.target.value)"
                    @keydown="handleUnitKeydown"
                    :disabled="!selectedProduct"
                    class="w-full h-10 px-2 rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-sm font-semibold"
                    :class="entryPhase === 'unit' ? 'ring-2 ring-primary' : ''">
                    <option v-for="opt in unitOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                    <option v-if="unitOptions.length === 0" :value="0">-</option>
                </select>
            </div>

            <!-- Price (readonly) -->
            <div class="w-32 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">HARGA</label>
                <InputText :value="formatRp(price)" readonly
                    class="w-full h-10 text-right font-bold bg-surface-100 dark:bg-surface-800" />
            </div>

            <!-- Qty -->
            <div class="w-20 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">QTY</label>
                <InputNumber ref="qtyRef" v-model="qty" :min="1"
                    class="w-full h-10" inputClass="w-full h-full text-center font-bold px-2 border border-surface-300 dark:border-surface-600 rounded-md"
                    :class="entryPhase === 'qty' ? 'ring-2 ring-primary rounded-md' : ''"
                    :disabled="!selectedProduct"
                    @keydown="handleQtyKeydown" />
            </div>

            <!-- Discount -->
            <div class="w-28 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">DISC (Rp)</label>
                <InputNumber ref="discRef" v-model="discount" :min="0"
                    class="w-full h-10" inputClass="w-full h-full text-right px-2 border border-surface-300 dark:border-surface-600 rounded-md"
                    :class="entryPhase === 'disc' ? 'ring-2 ring-primary rounded-md' : ''"
                    :disabled="!selectedProduct"
                    @keydown="handleDiscKeydown" />
            </div>
        </div>

        <!-- Lookup table -->
        <div v-if="lookupVisible && lookupResults.length > 0"
            class="border border-surface-300 dark:border-surface-600 rounded-lg overflow-hidden max-h-64 overflow-y-auto shadow-lg">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-100 dark:bg-surface-800 text-left text-xs font-semibold sticky top-0">
                        <th class="p-2">NAMA BARANG</th>
                        <th class="p-2 w-16">SAT1</th>
                        <th class="p-2 w-24 text-right">HARGA 1</th>
                        <th class="p-2 w-16">SAT2</th>
                        <th class="p-2 w-24 text-right">HARGA 2</th>
                        <th class="p-2 w-16 text-right">STOK</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in lookupResults" :key="p.id"
                        class="cursor-pointer transition-colors border-b border-surface-200 dark:border-surface-700"
                        :class="i === lookupIndex ? 'bg-primary/20 font-semibold' : 'hover:bg-surface-50 dark:hover:bg-surface-800'"
                        @click="selectProduct(p)"
                        @mouseenter="lookupIndex = i">
                        <td class="p-2">{{ p.name }}</td>
                        <td class="p-2">{{ p.units?.[0]?.unit_name || '-' }}</td>
                        <td class="p-2 text-right">{{ formatRp(p.units?.[0]?.[`price_${priceLevel}`] || p.units?.[0]?.price_h1 || 0) }}</td>
                        <td class="p-2">{{ p.units?.[1]?.unit_name || '' }}</td>
                        <td class="p-2 text-right">{{ p.units?.[1] ? formatRp(p.units[1][`price_${priceLevel}`] || p.units[1].price_h1 || 0) : '' }}</td>
                        <td class="p-2 text-right" :class="p.stock <= 0 ? 'text-red-500' : ''">{{ p.stock }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
