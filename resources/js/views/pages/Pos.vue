<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useCart } from '@/composables/useCart';
import { usePosData } from '@/composables/usePosData';
import { useHoldTransactions } from '@/composables/useHoldTransactions';
import { useShift } from '@/composables/useShift';
import { apiGet } from '@/composables/useApi';
import InlineEntry from '@/components/pos/InlineEntry.vue';
import ItemTable from '@/components/pos/ItemTable.vue';
import PaymentPanel from '@/components/pos/PaymentPanel.vue';
import HoldListDialog from '@/components/pos/HoldListDialog.vue';

const toast = useToast();
const { cartItems, clearCart, isEmpty, removeItem, transactionDiscount, updatePriceLevelForCart } = useCart();
const { loadPosData } = usePosData();
const { holdCurrentCart, resumeTransaction, holdCount } = useHoldTransactions();
const { activeShift } = useShift();

const inlineEntryRef = ref(null);
const paymentPanelRef = ref(null);
const holdDialogVisible = ref(false);
const selectedRowIndex = ref(-1);

// Customer / Member state
const selectedCustomer = ref({ id: null, name: 'UMUM', type: 'umum', price_level: 'h1' });
const priceLevel = ref('h1');
const customerQuery = ref('');
const customerResults = ref([]);
const customerDropdownVisible = ref(false);

// --- Customer search ---
async function searchCustomers(query) {
    if (!query || query.length < 1) {
        customerResults.value = [];
        customerDropdownVisible.value = false;
        return;
    }
    try {
        const res = await apiGet(`/api/customers/search?q=${encodeURIComponent(query)}`);
        customerResults.value = res.customers || [];
        customerDropdownVisible.value = customerResults.value.length > 0;
    } catch { /* ignore */ }
}

function selectCustomer(customer) {
    selectedCustomer.value = customer;
    priceLevel.value = customer.price_level || 'h1';
    customerQuery.value = customer.name;
    customerDropdownVisible.value = false;
}

function resetCustomer() {
    selectedCustomer.value = { id: null, name: 'UMUM', type: 'umum', price_level: 'h1' };
    priceLevel.value = 'h1';
    customerQuery.value = '';
    customerResults.value = [];
    customerDropdownVisible.value = false;
}

watch(priceLevel, (newLevel) => {
    updatePriceLevelForCart(newLevel);
});

// --- Event handlers ---
function handleItemAdded() {
    selectedRowIndex.value = cartItems.value.length - 1;
}

function handleGoToPayment() {
    if (isEmpty.value) return;
    paymentPanelRef.value?.focusBayar();
}

function handlePaymentSuccess() {
    selectedRowIndex.value = -1;
    resetCustomer();
    // Focus back to search
    setTimeout(() => inlineEntryRef.value?.focusSearch(), 200);
}

function handleHold() {
    if (isEmpty.value) return;
    try {
        holdCurrentCart(cartItems.value, transactionDiscount.value);
        clearCart();
        toast.add({ severity: 'info', summary: 'Ditahan', detail: 'Transaksi berhasil ditahan.', life: 2000 });
        inlineEntryRef.value?.focusSearch();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

function handleResume(index) {
    if (!isEmpty.value) {
        try {
            holdCurrentCart(cartItems.value, transactionDiscount.value);
        } catch (err) {
            toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            return;
        }
    }
    const held = resumeTransaction(index);
    if (!held) return;
    clearCart();
    held.items.forEach((item) => cartItems.value.push(item));
    transactionDiscount.value = held.transactionDiscount || 0;
    toast.add({ severity: 'success', summary: 'Dilanjutkan', detail: `${held.label} di-resume.`, life: 2000 });
}

// --- Keyboard Shortcuts ---
function handleKeyboard(e) {
    // F5 = New transaction
    if (e.key === 'F5') {
        e.preventDefault();
        clearCart();
        resetCustomer();
        selectedRowIndex.value = -1;
        setTimeout(() => inlineEntryRef.value?.focusSearch(), 100);
    }
    // F7 = Delete selected item
    if (e.key === 'F7') {
        e.preventDefault();
        if (selectedRowIndex.value >= 0 && selectedRowIndex.value < cartItems.value.length) {
            removeItem(selectedRowIndex.value);
            selectedRowIndex.value = Math.min(selectedRowIndex.value, cartItems.value.length - 1);
        }
    }
    // Alt+H = Hold
    if (e.altKey && e.key.toLowerCase() === 'h') {
        e.preventDefault();
        handleHold();
    }
    // Alt+R = Resume
    if (e.altKey && e.key.toLowerCase() === 'r') {
        e.preventDefault();
        if (holdCount.value > 0) holdDialogVisible.value = true;
    }
    // Escape
    if (e.key === 'Escape') {
        holdDialogVisible.value = false;
        customerDropdownVisible.value = false;
    }
}

onMounted(async () => {
    await loadPosData();
    document.addEventListener('keydown', handleKeyboard);
    // Auto focus search
    setTimeout(() => inlineEntryRef.value?.focusSearch(), 300);

    if (window.Echo) {
        window.Echo.channel('pos-channel')
            .listen('ProductStockUpdated', (e) => {
                // Update product cache
                const { updateProductStock } = usePosData();
                updateProductStock(e.productId, e.newStock);

                // Update cart items
                cartItems.value.forEach(item => {
                    if (item.itemType === 'product' && item.productId === e.productId) {
                        item.stock = e.newStock;
                    }
                });
            });
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyboard);
    if (window.Echo) {
        window.Echo.leaveChannel('pos-channel');
    }
});
</script>

<template>
    <!-- BLOKIR POS JIKA SHIFT KEMARIN BELUM DITUTUP -->
    <div v-if="isOldShift" class="absolute inset-0 z-[100] flex items-center justify-center bg-surface-900/80 backdrop-blur-sm">
        <div class="bg-surface-0 dark:bg-surface-800 p-8 rounded-2xl shadow-2xl max-w-lg text-center mx-4 flex flex-col items-center">
            <i class="pi pi-lock text-red-500 text-6xl mb-4"></i>
            <h2 class="text-3xl font-bold text-red-500 m-0 mb-2">POS TERKUNCI</h2>
            <p class="text-lg text-surface-600 dark:text-surface-300 mb-6">
                Anda memiliki shift yang masih berstatus BUKA dari tanggal <strong>{{ activeShift?.started_at?.substring(0, 10) }}</strong>. <br/><br/>
                Sistem mendeteksi hari sudah berganti. Anda <strong>wajib</strong> menghitung sisa uang laci dan menutup shift tersebut sebelum dapat melakukan transaksi penjualan di hari ini.
            </p>
            <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-semibold rounded-lg border border-red-200 dark:border-red-800 w-full animate-pulse">
                <i class="pi pi-arrow-up mr-2"></i> Klik tombol "Tutup Shift" di menu atas!
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 h-[calc(100vh-120px)]" :class="{ 'pointer-events-none opacity-20': isOldShift }">
        <!-- Left Panel: Entry + Table -->
        <div class="col-span-1 lg:col-span-9 flex flex-col gap-2 overflow-hidden">
            <!-- Header: Customer + Price Level -->
            <div class="card mb-0 p-3">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 relative flex-1">
                        <label class="text-sm font-semibold text-muted-color whitespace-nowrap">Customer:</label>
                        <div class="relative flex-1 max-w-xs">
                            <InputText v-model="customerQuery"
                                placeholder="UMUM" class="w-full"
                                @input="searchCustomers(customerQuery)"
                                @focus="searchCustomers(customerQuery)" />
                            <!-- Customer dropdown -->
                            <div v-if="customerDropdownVisible"
                                class="absolute top-full left-0 right-0 z-50 mt-1 bg-surface-0 dark:bg-surface-900 border border-surface-300 dark:border-surface-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <div v-for="c in customerResults" :key="c.id"
                                    class="px-3 py-2 cursor-pointer hover:bg-primary/10 text-sm"
                                    @click="selectCustomer(c)">
                                    <span class="font-semibold">{{ c.name }}</span>
                                    <span class="text-muted-color ml-2">({{ c.code }})</span>
                                    <Tag v-if="c.type === 'member'" value="Member" severity="info" class="ml-2" size="small" />
                                </div>
                            </div>
                        </div>
                        <Button v-if="selectedCustomer.name !== 'UMUM'" icon="pi pi-times" severity="secondary"
                            text size="small" @click="resetCustomer" />
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-muted-color">Harga:</label>
                        <SelectButton v-model="priceLevel" :options="[
                            { label: 'H1', value: 'h1' },
                            { label: 'H2', value: 'h2', disabled: selectedCustomer.type !== 'member' },
                            { label: 'H3', value: 'h3', disabled: selectedCustomer.type !== 'member' },
                        ]" optionLabel="label" optionValue="value" optionDisabled="disabled" />
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <Button v-if="holdCount > 0" :label="`Resume (${holdCount})`"
                            icon="pi pi-play" size="small" severity="warn" outlined
                            @click="holdDialogVisible = true" />
                        <Button icon="pi pi-pause" size="small" severity="secondary" outlined
                            :disabled="isEmpty" @click="handleHold" v-tooltip="'Hold (Alt+H)'" />
                    </div>
                </div>
            </div>

            <!-- Inline Entry -->
            <div class="card mb-0 p-3">
                <InlineEntry ref="inlineEntryRef" :priceLevel="priceLevel"
                    @item-added="handleItemAdded" @go-to-payment="handleGoToPayment" />
            </div>

            <!-- Item Table -->
            <div class="card mb-0 p-0 flex-1 overflow-hidden">
                <ItemTable :selectedIndex="selectedRowIndex" @select-row="selectedRowIndex = $event" />
            </div>

            <!-- Shortcuts bar -->
            <div class="flex gap-4 text-xs text-muted-color px-2 py-1">
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded">F5</kbd> New</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded">F7</kbd> Delete Item</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded">Alt+H</kbd> Hold</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded">Alt+R</kbd> Resume</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded">Enter</kbd> Navigasi</span>
            </div>
        </div>

        <!-- Right Panel: Payment -->
        <div class="col-span-1 lg:col-span-3">
            <div class="card h-full p-4">
                <PaymentPanel ref="paymentPanelRef"
                    :customerId="selectedCustomer.id"
                    :priceLevel="priceLevel"
                    @success="handlePaymentSuccess" />
            </div>
        </div>
    </div>

    <!-- Hold Dialog -->
    <HoldListDialog v-model:visible="holdDialogVisible" @resume="handleResume" />
</template>
