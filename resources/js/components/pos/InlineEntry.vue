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
const { searchProducts, updateProductStock, uniquePaperSizes, calculatePrintPrice } = usePosData();
const { addProductItem } = useCart();

// --- Refs ---
const searchRef = ref(null);
const unitRef = ref(null);
const qtyRef = ref(null);
const discRef = ref(null);
const paperRef = ref(null);
const colorRef = ref(null);
const sideRef = ref(null);
const priceRef = ref(null);

// --- State ---
const searchQuery = ref('');
const selectedProduct = ref(null);
const selectedUnitIndex = ref(0);
const price = ref(0);
const qty = ref(1);
const discount = ref(0);
const itemNotes = ref('');
const lookupResults = ref([]);
const lookupIndex = ref(0);
const lookupVisible = ref(false);
const entryPhase = ref('search'); // 'search' | 'unit' | 'qty' | 'disc'
const isPriceManual = ref(false);

const printPaperSize = ref('');
const printColorType = ref('bw');
const printSideType = ref('single');

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

watch([qty, printPaperSize, printColorType, printSideType], () => {
    if (selectedProduct.value && selectedProduct.value.type === 'print') {
        if (isPriceManual.value && qty.value === 1) return; // Allow manual override unless qty changes? No, if manual, just return? Actually, if manual, recalculating would overwrite. So return.
        if (isPriceManual.value) return; 
        
        const calc = calculatePrintPrice(printPaperSize.value, printColorType.value, printSideType.value, qty.value);
        if (calc) {
            price.value = calc.effectivePrice;
            selectedProduct.value.pp = calc;
        } else {
            price.value = 0;
            selectedProduct.value.pp = null;
        }
    }
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
    itemNotes.value = '';
    lookupResults.value = [];
    lookupVisible.value = false;
    lookupIndex.value = 0;
    entryPhase.value = 'search';
    isPriceManual.value = false;
}

function activatePrintMode() {
    selectProduct({
        id: 'print-generic',
        name: 'Jasa Cetak / Print (Pilih Opsi)',
        type: 'print',
        stock: 0,
        cost_price: 0,
        units: [{ level: 1, unit_name: 'LBR', base_multiplier: 1, price_h1: 0 }],
        barcode: null,
        is_generic: true
    });
}

function togglePrintMode() {
    if (selectedProduct.value && selectedProduct.value.type === 'print') {
        resetEntry();
        nextTick(() => {
            const el = searchRef.value?.$el || searchRef.value;
            const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
            if (input) { input.focus(); input.select(); }
        });
    } else {
        activatePrintMode();
    }
}

function selectProduct(product) {
    selectedProduct.value = product;
    searchQuery.value = product.name;
    lookupVisible.value = false;
    isPriceManual.value = false;
    
    // Set default unit (level 1, smallest)
    selectedUnitIndex.value = 0;
    
    if (product.type === 'print') {
        printPaperSize.value = uniquePaperSizes.value.length > 0 ? uniquePaperSizes.value[0] : 'A4';
        printColorType.value = 'bw';
        printSideType.value = 'single';
        
        // Trigger calculation explicitly since watch might not run immediately before price update
        const calc = calculatePrintPrice(printPaperSize.value, printColorType.value, printSideType.value, qty.value);
        if (calc) {
            price.value = calc.effectivePrice;
            product.pp = calc;
        } else {
            price.value = 0;
            product.pp = null;
        }
    } else {
        updatePrice();
    }
    
    // Move to next step based on type
    if (product.type === 'print') {
        entryPhase.value = 'print_paper';
        nextTick(() => {
            const el = paperRef.value?.$el || paperRef.value;
            if (el) el.focus();
        });
    } else {
        entryPhase.value = 'unit';
        nextTick(() => {
            const el = unitRef.value?.$el || unitRef.value;
            const input = el?.tagName === 'SELECT' ? el : el?.querySelector('select') || el?.querySelector('[role="combobox"]') || el?.querySelector('input');
            if (input) input.focus();
        });
    }
}

function confirmUnit() {
    if (selectedProduct.value && selectedProduct.value.type === 'jasa') {
        entryPhase.value = 'price';
        nextTick(() => {
            const el = priceRef.value?.$el || priceRef.value;
            const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
            if (input) { input.focus(); input.select(); }
        });
    } else {
        entryPhase.value = 'qty';
        nextTick(() => {
            const el = qtyRef.value?.$el || qtyRef.value;
            const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
            if (input) { input.focus(); input.select(); }
        });
    }
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
    let finalDesc = p.name;
    if (itemNotes.value) finalDesc += ` (${itemNotes.value})`;

    if (p.type === 'print') {
        const { addPrintItem } = useCart();
        
        // Ensure manual price also gets a fallback cost_price if not matched
        const costPerSheet = p.pp ? p.pp.costPerSheet : 0;
        const printPriceId = p.pp ? p.pp.printPriceId : null;
        
        addPrintItem({
            paperSize: printPaperSize.value,
            colorType: printColorType.value,
            sideType: printSideType.value,
            qty: qty.value,
            unitPrice: price.value,
            costPerSheet: costPerSheet,
            printPriceId: printPriceId,
            isCustom: false,
            addons: [],
            discount: discount.value,
            notes: itemNotes.value
        });
        const labelStr = `Print ${printPaperSize.value} ${printColorType.value === 'bw' ? 'Hitam Putih' : 'Warna'}`;
        toast.add({ severity: 'success', summary: 'Ditambahkan', detail: `${labelStr} x${qty.value}`, life: 1500 });
    } else {
        addProductItem({
            id: p.id,
            name: finalDesc,
            product_code: p.product_code,
            barcode: p.barcode,
            type: p.type,
            stock: p.stock,
            cost_price: p.cost_price,
            units: p.units,
        }, qty.value, u, props.priceLevel, price.value, discount.value);
        toast.add({ severity: 'success', summary: 'Ditambahkan', detail: `${finalDesc} x${qty.value}`, life: 1500 });
    }

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
        } else if (searchQuery.value && e.ctrlKey) {
            // Custom item
            selectProduct({
                id: 'custom-' + Date.now(),
                name: searchQuery.value,
                type: 'jasa',
                stock: 0,
                cost_price: 0,
                units: [{ level: 1, unit_name: 'PCS', base_multiplier: 1, price_h1: 0 }],
                barcode: null
            });
        } else if (!searchQuery.value && entryPhase.value === 'search') {
            // Empty search + Enter = go to payment
            emit('go-to-payment');
        }
    } else if (e.key === 'Escape') {
        if (selectedProduct.value && selectedProduct.value.type === 'print') {
            resetEntry();
            nextTick(() => searchRef.value?.$el?.focus());
        } else {
            lookupVisible.value = false;
        }
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

function handlePaperKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        entryPhase.value = 'print_color';
        nextTick(() => {
            const el = colorRef.value?.$el || colorRef.value;
            if (el) el.focus();
        });
    } else if (e.key === 'Escape') {
        resetEntry();
        nextTick(() => searchRef.value?.$el?.focus());
    }
}

function handleColorKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        entryPhase.value = 'print_side';
        nextTick(() => {
            const el = sideRef.value?.$el || sideRef.value;
            if (el) el.focus();
        });
    } else if (e.key === 'Escape') {
        resetEntry();
        nextTick(() => searchRef.value?.$el?.focus());
    }
}

function handleSideKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        entryPhase.value = 'price';
        nextTick(() => {
            const el = priceRef.value?.$el || priceRef.value;
            const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
            if (input) { input.focus(); input.select(); }
        });
    } else if (e.key === 'Escape') {
        resetEntry();
        nextTick(() => searchRef.value?.$el?.focus());
    }
}

function handlePriceKeydown(e) {
    if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        entryPhase.value = 'qty';
        nextTick(() => {
            const el = qtyRef.value?.$el || qtyRef.value;
            const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
            if (input) { input.focus(); input.select(); }
        });
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
                <div class="flex gap-2">
                    <Button icon="pi pi-print" severity="secondary" @click="togglePrintMode" class="h-10 w-10 p-0 shrink-0" 
                        :outlined="!selectedProduct || selectedProduct.type !== 'print'" 
                        :class="{'ring-2 ring-primary bg-primary/10': selectedProduct && selectedProduct.type === 'print'}"
                        title="Toggle Mode Print" />
                    <InputText ref="searchRef" v-model="searchQuery"
                        placeholder="Scan barcode, ketik kode, atau nama..."
                        class="w-full font-mono h-10"
                        :class="entryPhase === 'search' ? 'ring-2 ring-primary' : ''"
                        @keydown="handleSearchKeydown"
                        @focus="entryPhase = 'search'"
                        :readonly="selectedProduct && selectedProduct.type === 'print'" />
                </div>
            </div>

            <!-- Unit dropdown (Hide if Print) -->
            <div v-if="!selectedProduct || selectedProduct.type !== 'print'" class="w-28 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">SATUAN</label>
                <select ref="unitRef"
                    :value="selectedUnitIndex"
                    @change="selectedUnitIndex = Number($event.target.value)"
                    @keydown="handleUnitKeydown"
                    :disabled="!selectedProduct"
                    class="w-full h-10 px-2 rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-sm font-semibold focus:ring-2 focus:ring-primary outline-none"
                    :class="entryPhase === 'unit' ? 'ring-2 ring-primary' : ''">
                    <option v-for="opt in unitOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                    <option v-if="unitOptions.length === 0" :value="0">-</option>
                </select>
            </div>

            <!-- Print Options Inline -->
            <template v-if="selectedProduct && selectedProduct.type === 'print'">
                <div class="w-32 flex flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-color">UKURAN</label>
                    <select ref="paperRef" v-model="printPaperSize" @change="isPriceManual = false" @keydown="handlePaperKeydown" class="w-full h-10 px-2 rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-sm font-semibold focus:ring-2 focus:ring-primary outline-none" :class="entryPhase === 'print_paper' ? 'ring-2 ring-primary' : ''">
                        <option v-for="sz in uniquePaperSizes" :key="sz" :value="sz">{{ sz }}</option>
                    </select>
                </div>
                <div class="w-28 flex flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-color">TINTA</label>
                    <select ref="colorRef" v-model="printColorType" @change="isPriceManual = false" @keydown="handleColorKeydown" class="w-full h-10 px-2 rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-sm font-semibold focus:ring-2 focus:ring-primary outline-none" :class="entryPhase === 'print_color' ? 'ring-2 ring-primary' : ''">
                        <option value="bw">Hitam Putih</option>
                        <option value="color">Warna</option>
                    </select>
                </div>
                <div class="w-28 flex flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-color">SISI</label>
                    <select ref="sideRef" v-model="printSideType" @change="isPriceManual = false" @keydown="handleSideKeydown" class="w-full h-10 px-2 rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-sm font-semibold focus:ring-2 focus:ring-primary outline-none" :class="entryPhase === 'print_side' ? 'ring-2 ring-primary' : ''">
                        <option value="single">1 Sisi</option>
                        <option value="duplex">Bolak-balik</option>
                    </select>
                </div>
            </template>

            <!-- Price -->
            <div class="w-32 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">HARGA</label>
                <InputNumber ref="priceRef" :modelValue="price" @update:modelValue="val => { price = val; isPriceManual = true; }" mode="currency" currency="IDR" locale="id-ID" :min="0"
                    class="w-full h-10" inputClass="w-full h-full text-right font-bold px-2 border border-surface-300 dark:border-surface-600 rounded-md"
                    :disabled="!selectedProduct" 
                    :readonly="selectedProduct && (selectedProduct.type === 'barang')"
                    :class="[
                        (selectedProduct && (selectedProduct.type === 'barang')) ? 'bg-surface-100 dark:bg-surface-800' : '',
                        entryPhase === 'price' ? 'ring-2 ring-primary rounded-md' : ''
                    ]"
                    @keydown="handlePriceKeydown" />
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



        <!-- Jasa/Print Notes Row -->
        <div v-if="selectedProduct && (selectedProduct.type === 'jasa' || selectedProduct.type === 'print')" class="flex gap-2 items-end">
            <div class="flex-1 flex flex-col gap-1">
                <label class="text-xs font-semibold text-muted-color">KETERANGAN / CATATAN (Opsional)</label>
                <InputText v-model="itemNotes" placeholder="Misal: Spanduk 3x2m, atau Revisi 1" class="w-full h-10"
                    @keydown.enter="confirmDisc" />
            </div>
        </div>

        <!-- Lookup table -->
        <div v-if="lookupVisible && lookupResults.length > 0"
            class="border border-surface-300 dark:border-surface-600 rounded-lg overflow-hidden max-h-64 overflow-y-auto shadow-lg">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-100 dark:bg-surface-800 text-left text-xs font-semibold sticky top-0">
                        <th class="p-2 w-20">KODE</th>
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
                        <td class="p-2 font-mono font-bold">{{ p.product_code || '-' }}</td>
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
