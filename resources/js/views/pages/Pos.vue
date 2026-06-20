<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useCart } from '@/composables/useCart';
import { usePosData } from '@/composables/usePosData';
import { useHoldTransactions } from '@/composables/useHoldTransactions';
import { useShift } from '@/composables/useShift';
import PrintForm from '@/components/pos/PrintForm.vue';
import ProductSearch from '@/components/pos/ProductSearch.vue';
import CartPanel from '@/components/pos/CartPanel.vue';
import HoldListDialog from '@/components/pos/HoldListDialog.vue';
import PaymentDialog from '@/components/pos/PaymentDialog.vue';

const toast = useToast();
const { cartItems, addPrintItem, addProductItem, addAddonToItem, clearCart, isEmpty, transactionDiscount } = useCart();
const { loadPosData } = usePosData();
const { holdCurrentCart, resumeTransaction, holdCount } = useHoldTransactions();
const { activeShift } = useShift();

const activeTab = ref(0);
const holdDialogVisible = ref(false);
const paymentDialogVisible = ref(false);
const productSearchRef = ref(null);

watch(activeTab, (val) => {
    if (val === 1) {
        setTimeout(() => productSearchRef.value?.focusInput(), 100);
    }
});

// --- Event handlers ---
function handleAddPrint(data) {
    addPrintItem(data);
    toast.add({ severity: 'success', summary: 'Ditambahkan', detail: `${data.paperSize} ${data.qty} lembar`, life: 1500 });
}

function handleAddProduct(product, qty) {
    if (product.type === 'barang' && product.stock <= 0) {
        toast.add({ severity: 'warn', summary: 'Stok Habis', detail: `${product.name} tidak tersedia.`, life: 3000 });
        return;
    }
    addProductItem(product, qty);
    toast.add({ severity: 'success', summary: 'Ditambahkan', detail: product.name, life: 1500 });
}

function handleAddAddon(itemIndex, addon) {
    // No longer used directly here, Addon added via PrintForm
}

function handleHold() {
    if (isEmpty.value) return;
    try {
        holdCurrentCart(cartItems.value, transactionDiscount.value);
        clearCart();
        toast.add({ severity: 'info', summary: 'Ditahan', detail: 'Transaksi berhasil ditahan.', life: 2000 });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    }
}

function handleResume(index) {
    if (!isEmpty.value) {
        try {
            holdCurrentCart(cartItems.value, transactionDiscount.value);
            toast.add({ severity: 'info', summary: 'Otomatis Ditahan', detail: 'Keranjang aktif otomatis ditahan.', life: 3000 });
        } catch (err) {
            toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
            return;
        }
    }

    const held = resumeTransaction(index);
    if (!held) return;

    clearCart();
    held.items.forEach((item) => {
        cartItems.value.push(item);
    });
    transactionDiscount.value = held.transactionDiscount || 0;

    toast.add({ severity: 'success', summary: 'Dilanjutkan', detail: `${held.label} di-resume.`, life: 2000 });
}

function handlePay() {
    if (!activeShift.value) {
        toast.add({ severity: 'warn', summary: 'Shift Belum Dibuka', detail: 'Buka shift terlebih dahulu.', life: 3000 });
        return;
    }
    if (isEmpty.value) return;
    paymentDialogVisible.value = true;
}

function handlePaymentSuccess() {
    paymentDialogVisible.value = false;
    // Focus back to search after successful checkout
    activeTab.value = 1;
    setTimeout(() => productSearchRef.value?.focusInput(), 200);
}

// --- Keyboard Shortcuts (Alt + key) ---
function handleKeyboard(e) {
    if (!e.altKey) {
        if (e.key === 'Escape') {
            holdDialogVisible.value = false;
            paymentDialogVisible.value = false;
        }
        return;
    }

    const key = e.key.toLowerCase();

    // Alt+S = Search produk
    if (key === 's') {
        e.preventDefault();
        activeTab.value = 1;
        setTimeout(() => productSearchRef.value?.focusInput(), 100);
    }
    // Alt+C = tab Cetak
    if (key === 'c') {
        e.preventDefault();
        activeTab.value = 0;
    }
    // Alt+B = Bayar
    if (key === 'b') {
        e.preventDefault();
        handlePay();
    }
    // Alt+H = Hold
    if (key === 'h') {
        e.preventDefault();
        handleHold();
    }
    // Alt+R = Resume hold
    if (key === 'r') {
        e.preventDefault();
        if (holdCount.value > 0) holdDialogVisible.value = true;
    }
}

onMounted(async () => {
    await loadPosData();
    document.addEventListener('keydown', handleKeyboard);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyboard);
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 min-h-[calc(100vh-120px)] lg:h-[calc(100vh-120px)]">
        <!-- Left Panel: Input -->
        <div class="col-span-1 lg:col-span-7 flex flex-col gap-3">
            <!-- Tabs: Cetak | Produk | Addon -->
            <div class="card mb-0 p-3">
                <div class="flex items-center justify-between mb-3">
                    <TabMenu :model="[
                        { label: 'Jasa Cetak & Addon (Alt+C)', icon: 'pi pi-print' },
                        { label: 'Produk ATK (Alt+S)', icon: 'pi pi-box' },
                    ]" v-model:activeIndex="activeTab" />
                    <div class="flex items-center gap-2">
                        <Button v-if="holdCount > 0"
                            :label="`Resume (${holdCount})`"
                            icon="pi pi-play" size="small" severity="warn" outlined
                            @click="holdDialogVisible = true" />
                    </div>
                </div>

                <!-- Tab Content -->
                <div v-show="activeTab === 0">
                    <PrintForm @add="handleAddPrint" />
                </div>
                <div v-show="activeTab === 1">
                    <ProductSearch ref="productSearchRef" @add="handleAddProduct" />
                </div>
            </div>

            <!-- Shortcuts info -->
            <div class="text-xs text-muted-color flex gap-4 px-2">
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">Alt+S</kbd> Cari Produk</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">Alt+C</kbd> Jasa Cetak</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">Alt+B</kbd> Bayar</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">Alt+H</kbd> Hold</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">Alt+R</kbd> Resume</span>
            </div>
        </div>

        <!-- Right Panel: Cart -->
        <div class="col-span-1 lg:col-span-5">
            <div class="card h-full p-4">
                <CartPanel @pay="handlePay" @hold="handleHold" />
            </div>
        </div>
    </div>

    <!-- Hold List Dialog -->
    <HoldListDialog v-model:visible="holdDialogVisible" @resume="handleResume" />

    <!-- Payment Dialog -->
    <PaymentDialog v-model:visible="paymentDialogVisible" @success="handlePaymentSuccess" />
</template>
