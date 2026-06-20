<script setup>
import { ref, computed, watch } from 'vue';
import { useCart } from '@/composables/useCart';
import { useShift } from '@/composables/useShift';
import { apiPost, apiGet } from '@/composables/useApi';
import { useToast } from 'primevue/usetoast';

const props = defineProps({ visible: Boolean });
const emit = defineEmits(['update:visible', 'success']);

const toast = useToast();
const { cartItems, grandTotal, subtotal, transactionDiscount, clearCart } = useCart();
const { activeShift } = useShift();

const paymentMethod = ref('cash');
const cashPaid = ref(0);
const notes = ref('');
const submitting = ref(false);
const receiptData = ref(null);
const showReceipt = ref(false);

const paymentMethods = [
    { label: 'Tunai', value: 'cash', icon: 'pi pi-money-bill' },
    { label: 'QRIS', value: 'qris', icon: 'pi pi-qrcode' },
    { label: 'Transfer', value: 'transfer', icon: 'pi pi-building' },
];

const cashChange = computed(() => {
    if (paymentMethod.value !== 'cash') return 0;
    return Math.max(0, cashPaid.value - grandTotal.value);
});

const isPaymentValid = computed(() => {
    if (paymentMethod.value === 'cash') {
        return cashPaid.value >= grandTotal.value;
    }
    return true;
});

const quickAmounts = computed(() => {
    const total = grandTotal.value;
    const amounts = [];
    // Exact amount
    amounts.push(total);
    // Round up to nearest 5000, 10000, 50000, 100000
    [5000, 10000, 20000, 50000, 100000].forEach((r) => {
        const rounded = Math.ceil(total / r) * r;
        if (rounded > total && !amounts.includes(rounded)) {
            amounts.push(rounded);
        }
    });
    return amounts.slice(0, 5);
});

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

async function handleCheckout() {
    if (!isPaymentValid.value) return;
    submitting.value = true;

    try {
        // Prepare cart items for API
        const items = cartItems.value.map((item) => ({
            itemType: item.itemType,
            productId: item.productId ?? null,
            printPriceId: item.printPriceId ?? null,
            description: item.description,
            qty: item.qty,
            unitPrice: item.unitPrice,
            costPrice: item.costPrice ?? item.costPerSheet ?? 0,
            discount: item.discount ?? 0,
            addons: (item.addons || []).map((a) => ({
                addonServiceId: a.addonServiceId,
                name: a.name,
                price: a.price,
                qty: a.qty ?? 1,
            })),
        }));

        const result = await apiPost('/api/transactions/checkout', {
            items,
            payment_method: paymentMethod.value,
            cash_paid: paymentMethod.value === 'cash' ? cashPaid.value : grandTotal.value,
            discount: transactionDiscount.value,
            notes: notes.value || null,
        });

        toast.add({
            severity: 'success',
            summary: 'Transaksi Berhasil!',
            detail: `${result.transaction.invoice_number} — ${formatRp(result.transaction.total)}`,
            life: 4000,
        });

        // Load receipt data
        const receiptRes = await apiGet(`/api/transactions/${result.transaction.id}/receipt`);
        receiptData.value = receiptRes.receipt;
        showReceipt.value = true;

        clearCart();
        emit('success', result.transaction);

    } catch (err) {
        toast.add({ severity: 'error', summary: 'Checkout Gagal', detail: err.message, life: 5000 });
    } finally {
        submitting.value = false;
    }
}

function closeAll() {
    showReceipt.value = false;
    receiptData.value = null;
    emit('update:visible', false);
}

// Reset on open
watch(() => props.visible, (val) => {
    if (val) {
        paymentMethod.value = 'cash';
        cashPaid.value = 0;
        notes.value = '';
        showReceipt.value = false;
        receiptData.value = null;
    }
});
</script>

<template>
    <!-- Payment Dialog -->
    <Dialog :visible="visible && !showReceipt" @update:visible="emit('update:visible', $event)"
        header="Pembayaran" modal :closable="!submitting" :style="{ width: '520px' }">

        <!-- Payment Summary -->
        <div class="p-4 bg-primary/10 rounded-lg mb-4">
            <div class="flex justify-between items-center">
                <span class="text-lg text-muted-color">Total Bayar</span>
                <span class="text-3xl font-bold text-primary">{{ formatRp(grandTotal) }}</span>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Metode Pembayaran</label>
                <SelectButton v-model="paymentMethod" :options="paymentMethods"
                    optionLabel="label" optionValue="value" />
            </div>

            <!-- Cash Input -->
            <div v-if="paymentMethod === 'cash'" class="flex flex-col gap-3">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Uang Diterima (Rp)</label>
                    <InputNumber v-model="cashPaid" mode="currency" currency="IDR" locale="id-ID"
                        :min="0" class="w-full" inputClass="text-2xl font-bold text-center" autofocus />
                </div>

                <!-- Quick amounts -->
                <div class="flex flex-wrap gap-2">
                    <Button v-for="amount in quickAmounts" :key="amount"
                        :label="formatRp(amount)" size="small" outlined
                        :severity="cashPaid === amount ? 'primary' : 'secondary'"
                        @click="cashPaid = amount" />
                </div>

                <!-- Change display -->
                <div class="p-4 rounded-lg" :class="cashChange >= 0 && cashPaid >= grandTotal ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                    <span class="text-muted-color">Kembalian:</span>
                    <span class="text-2xl font-bold ml-2"
                        :class="cashPaid >= grandTotal ? 'text-green-600 dark:text-green-400' : 'text-red-500'">
                        {{ cashPaid >= grandTotal ? formatRp(cashChange) : 'Kurang' }}
                    </span>
                </div>
            </div>

            <!-- Non-cash info -->
            <div v-else class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="m-0 text-sm">
                    <i class="pi pi-info-circle mr-2"></i>
                    Pembayaran {{ paymentMethod === 'qris' ? 'QRIS' : 'Transfer Bank' }} —
                    Pastikan pelanggan sudah melakukan pembayaran sebelum konfirmasi.
                </p>
            </div>

            <!-- Notes -->
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Catatan (Opsional)</label>
                <InputText v-model="notes" placeholder="Catatan transaksi..." />
            </div>
        </div>

        <template #footer>
            <Button label="Batal" severity="secondary" text @click="emit('update:visible', false)" :disabled="submitting" />
            <Button label="Konfirmasi Pembayaran" icon="pi pi-check" severity="success"
                :loading="submitting" :disabled="!isPaymentValid" @click="handleCheckout" />
        </template>
    </Dialog>

    <!-- Receipt Dialog -->
    <Dialog :visible="showReceipt" header="Struk Transaksi" modal :style="{ width: '420px' }" @update:visible="closeAll">
        <div v-if="receiptData" class="font-mono text-sm">
            <div class="text-center mb-3">
                <p class="font-bold text-lg m-0">{{ receiptData.store_name }}</p>
                <p class="text-xs text-muted-color m-0">{{ receiptData.store_address }}</p>
                <p class="text-xs m-0 mt-1">{{ receiptData.date }}</p>
                <p class="text-xs m-0">No: {{ receiptData.invoice_number }}</p>
                <p class="text-xs m-0">Kasir: {{ receiptData.cashier }}</p>
            </div>
            <hr class="border-dashed" />

            <div v-for="(item, i) in receiptData.items" :key="i" class="py-1">
                <div class="flex justify-between">
                    <span>{{ item.description }}</span>
                </div>
                <div class="flex justify-between text-muted-color">
                    <span>{{ item.qty }} x {{ formatRp(item.unit_price) }}</span>
                    <span>{{ formatRp(item.subtotal) }}</span>
                </div>
                <div v-for="(addon, ai) in item.addons" :key="ai" class="flex justify-between text-xs text-muted-color ml-2">
                    <span>+ {{ addon.description }}</span>
                    <span>{{ formatRp(addon.price) }}</span>
                </div>
            </div>

            <hr class="border-dashed" />

            <div class="flex justify-between py-1">
                <span>Subtotal</span>
                <span>{{ formatRp(receiptData.subtotal) }}</span>
            </div>
            <div v-if="receiptData.discount > 0" class="flex justify-between py-1 text-red-500">
                <span>Diskon</span>
                <span>-{{ formatRp(receiptData.discount) }}</span>
            </div>
            <div class="flex justify-between py-1 font-bold text-lg">
                <span>TOTAL</span>
                <span>{{ formatRp(receiptData.total) }}</span>
            </div>

            <hr class="border-dashed" />

            <div class="flex justify-between py-1">
                <span>Bayar ({{ receiptData.payment_method.toUpperCase() }})</span>
                <span>{{ formatRp(receiptData.cash_paid) }}</span>
            </div>
            <div v-if="receiptData.payment_method === 'cash'" class="flex justify-between py-1">
                <span>Kembali</span>
                <span>{{ formatRp(receiptData.cash_change) }}</span>
            </div>

            <hr class="border-dashed" />
            <p class="text-center text-xs text-muted-color mt-2 m-0">Terima kasih atas kunjungan Anda!</p>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="closeAll" />
        </template>
    </Dialog>
</template>
