<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useCart } from '@/composables/useCart';
import { usePosData } from '@/composables/usePosData';
import { useHoldTransactions } from '@/composables/useHoldTransactions';
import { useShift } from '@/composables/useShift';
import PrintForm from '@/components/pos/PrintForm.vue';
import ProductSearch from '@/components/pos/ProductSearch.vue';
import AddonPicker from '@/components/pos/AddonPicker.vue';
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
    addAddonToItem(itemIndex, addon);
    toast.add({ severity: 'info', summary: 'Addon', detail: `${addon.name} ditambahkan`, life: 1500 });
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

// --- Keyboard Shortcuts ---
function handleKeyboard(e) {
    // F1 = fokus ke pencarian produk
    if (e.key === 'F1') {
        e.preventDefault();
        activeTab.value = 1;
        setTimeout(() => productSearchRef.value?.focusInput(), 100);
    }
    // F2 = tab cetak
    if (e.key === 'F2') {
        e.preventDefault();
        activeTab.value = 0;
    }
    // F5 = bayar
    if (e.key === 'F5') {
        e.preventDefault();
        handlePay();
    }
    // F8 = hold
    if (e.key === 'F8') {
        e.preventDefault();
        handleHold();
    }
    // Escape = clear / close dialogs
    if (e.key === 'Escape') {
        holdDialogVisible.value = false;
        paymentDialogVisible.value = false;
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
    <div class="grid grid-cols-12 gap-4" style="height: calc(100vh - 120px);">
        <!-- Left Panel: Input -->
        <div class="col-span-7 flex flex-col gap-3">
            <!-- Tabs: Cetak | Produk | Addon -->
            <div class="card mb-0 p-3">
                <div class="flex items-center justify-between mb-3">
                    <TabMenu :model="[
                        { label: 'Jasa Cetak (F2)', icon: 'pi pi-print' },
                        { label: 'Produk ATK (F1)', icon: 'pi pi-box' },
                        { label: 'Addon', icon: 'pi pi-plus-circle' },
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
                <div v-show="activeTab === 2">
                    <AddonPicker :cartItems="cartItems" @add="handleAddAddon" />
                </div>
            </div>

            <!-- Shortcuts info -->
            <div class="text-xs text-muted-color flex gap-4 px-2">
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">F1</kbd> Cari Produk</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">F2</kbd> Jasa Cetak</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">F5</kbd> Bayar</span>
                <span><kbd class="px-1 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-xs">F8</kbd> Hold</span>
            </div>
        </div>

        <!-- Right Panel: Cart -->
        <div class="col-span-5">
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
