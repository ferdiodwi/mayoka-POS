<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useCart } from '@/composables/useCart';
import { useShift } from '@/composables/useShift';
import { apiPost } from '@/composables/useApi';
import { useToast } from 'primevue/usetoast';
import { formatRp } from '@/utils/format';
import { useQzTray } from '@/composables/useQzTray';

const emit = defineEmits(['success']);
const toast = useToast();
const { printReceipt: printViaQzTray } = useQzTray();
const { cartItems, grandTotal, subtotal, transactionDiscount, setTransactionDiscount, clearCart, isEmpty } = useCart();
const { activeShift } = useShift();

const props = defineProps({
    customerId: { type: Number, default: null },
    priceLevel: { type: String, default: 'h1' },
});

const paymentMethod = ref('cash');
const cashPaid = ref(0);
const potongan = ref(0);
const submitting = ref(false);
const bayarRef = ref(null);

const paymentMethods = [
    { label: 'Cash', value: 'cash' },
    { label: 'QRIS', value: 'qris' },
    { label: 'Transfer', value: 'transfer' },
];

const cashChange = computed(() => {
    if (paymentMethod.value !== 'cash') return 0;
    return Math.max(0, cashPaid.value - grandTotal.value);
});

const isPaymentValid = computed(() => {
    if (isEmpty.value) return false;
    if (paymentMethod.value === 'cash') return cashPaid.value >= grandTotal.value;
    return true;
});

// Sync potongan with transactionDiscount
watch(potongan, (val) => setTransactionDiscount(val || 0));

function focusBayar() {
    cashPaid.value = grandTotal.value; // Auto uang pas
    nextTick(() => {
        const el = bayarRef.value?.$el;
        const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
        if (input) { input.focus(); input.select(); }
    });
}

async function handleCheckout() {
    if (!isPaymentValid.value || submitting.value) return;
    if (!activeShift.value) {
        toast.add({ severity: 'warn', summary: 'Shift Belum Dibuka', detail: 'Buka shift terlebih dahulu.', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        const items = cartItems.value.map((item) => ({
            itemType: item.itemType,
            productId: item.productId ?? null,
            printPriceId: item.printPriceId ?? null,
            description: item.description,
            qty: item.qty,
            unitPrice: item.unitPrice,
            costPrice: item.costPrice ?? item.costPerSheet ?? 0,
            discount: item.discount ?? 0,
            unitName: item.unitName ?? 'PCS',
            baseMultiplier: item.baseMultiplier ?? 1,
            addons: (item.addons || []).map((a) => ({
                addonServiceId: a.addonServiceId,
                name: a.name,
                price: a.price,
                qty: a.qty ?? 1,
            })),
        }));

        const result = await apiPost('/api/transactions/checkout', {
            items,
            customer_id: props.customerId,
            price_level: props.priceLevel,
            payment_method: paymentMethod.value,
            cash_paid: paymentMethod.value === 'cash' ? cashPaid.value : grandTotal.value,
            discount: transactionDiscount.value,
            notes: null,
        });

        toast.add({
            severity: 'success',
            summary: 'Transaksi Berhasil!',
            detail: `${result.transaction.invoice_number} — ${formatRp(result.transaction.total)}`,
            life: 3000,
        });

        if (result.print_error) {
            toast.add({ severity: 'warn', summary: 'Gagal Generate Struk', detail: result.print_error, life: 7000 });
        } else if (result.receipt_base64) {
            try {
                await printViaQzTray(result.receipt_base64);
            } catch (printErr) {
                console.error("Auto-print failed:", printErr);
            }
        }

        clearCart();
        potongan.value = 0;
        cashPaid.value = 0;
        paymentMethod.value = 'cash';
        emit('success', result.transaction);
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 5000 });
    } finally {
        submitting.value = false;
    }
}


function handleBayarKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        handleCheckout();
    }
}

defineExpose({ focusBayar });
</script>

<template>
    <div class="flex flex-col gap-3 h-full">
        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Total</label>
            <div class="text-2xl font-bold font-mono text-right">{{ formatRp(subtotal) }}</div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Potongan</label>
            <InputNumber v-model="potongan" :min="0" mode="currency" currency="IDR" locale="id-ID"
                class="w-full" inputClass="text-right text-sm" />
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Grand Total</label>
            <div class="text-3xl font-bold font-mono text-right text-primary p-3 bg-primary/10 rounded-lg">
                {{ formatRp(grandTotal) }}
            </div>
        </div>

        <hr class="border-surface-200 dark:border-surface-700" />

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Metode</label>
            <SelectButton v-model="paymentMethod" :options="paymentMethods" optionLabel="label" optionValue="value" class="w-full" />
        </div>

        <div v-if="paymentMethod === 'cash'" class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Bayar</label>
            <InputNumber ref="bayarRef" v-model="cashPaid" @input="cashPaid = $event.value || 0" :min="0" mode="currency" currency="IDR" locale="id-ID"
                class="w-full" inputClass="text-right text-xl font-bold"
                @keydown="handleBayarKeydown" />
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-muted-color">Kembali</label>
            <div class="text-2xl font-bold font-mono text-right p-3 rounded-lg"
                :class="paymentMethod !== 'cash' || cashPaid >= grandTotal ? 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20' : 'text-red-500 bg-red-50 dark:bg-red-900/20'">
                {{ paymentMethod !== 'cash' ? formatRp(0) : (cashPaid >= grandTotal ? formatRp(cashChange) : 'Kurang') }}
            </div>
        </div>

        <div class="mt-auto">
            <Button label="Bayar (Enter)" icon="pi pi-check" severity="success" class="w-full h-12"
                :loading="submitting" :disabled="!isPaymentValid" @click="handleCheckout" />
        </div>
    </div>
</template>
