<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useCart } from '@/composables/useCart';
import { useShift } from '@/composables/useShift';
import { apiPost } from '@/composables/useApi';
import { useToast } from 'primevue/usetoast';
import { formatRp } from '@/utils/format';

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
const cashPaidInput = ref(null);

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
    // Exact amount (Uang Pas)
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

        // Use receipt from checkout response directly (no extra API call)
        receiptData.value = result.receipt;
        showReceipt.value = true;

        // Auto print immediately
        await nextTick();
        printReceipt();

        clearCart();
        closeAll();
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

function printReceipt() {
    const printContent = document.getElementById('payment-receipt-print-area');
    if (!printContent) return;

    // Buat container sementara langsung di bawah body
    const tempDiv = document.createElement('div');
    tempDiv.id = 'temp-print-container';
    tempDiv.innerHTML = printContent.innerHTML;
    document.body.appendChild(tempDiv);

    // Tambahkan style cetak sementara untuk menyembunyikan elemen lain
    const style = document.createElement('style');
    style.id = 'temp-print-style';
    style.innerHTML = `
        @media print {
            body > * {
                display: none !important;
            }
            #temp-print-container {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            @page {
                margin: 0;
                size: 58mm auto;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; font-weight: bold; }
            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 10px;
                width: 100%;
                max-width: 45mm;
                margin: 0;
                padding: 0mm 4mm 15mm 0mm;
                color: #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            p { margin: 0; padding: 0; }
            .text-center { text-align: center; }
            .text-xs { font-size: 9px; }
            .text-sm { font-size: 10px; }
            .text-lg { font-size: 12px; font-weight: bold; }
            .font-bold { font-weight: bold; }
            .font-mono { font-family: Arial, Helvetica, sans-serif; }
            .text-muted-color { color: #000; }
            .text-red-500 { color: #000; }
            .border-dashed { border: none; border-top: 1px dashed #000; margin: 5px 0; }
            .flex { display: flex; }
            .justify-between { justify-content: space-between; }
            .py-1 { padding-top: 3px; padding-bottom: 3px; }
            .mt-2 { margin-top: 5px; }
            .mb-3 { margin-bottom: 8px; }
            .ml-2 { margin-left: 4px; }
            .total-row { font-size: 11px; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin: 3px 0; }
            .print-spacer { display: block !important; height: 60mm; }
            .print-spacer p { margin: 0; padding: 0; line-height: 1.5; font-size: 12px; }
        }
    `;
    document.head.appendChild(style);

    // Panggil print pada main window agar --kiosk-printing terdeteksi
    window.print();

    // Hapus kembali elemen sementara setelah selesai cetak
    document.body.removeChild(tempDiv);
    document.head.removeChild(style);
}

function focusCashInput() {
    const el = cashPaidInput.value?.$el;
    if (!el) return;
    if (el.tagName === 'INPUT') {
        el.focus();
        el.select();
    } else {
        const input = el.querySelector('input');
        if (input) {
            input.focus();
            input.select();
        }
    }
}

function handleEnterCheckout() {
    if (isPaymentValid.value && !submitting.value) {
        handleCheckout();
    }
}

function selectQuickAmount(amount) {
    cashPaid.value = amount;
    nextTick(() => {
        focusCashInput();
    });
}

// Reset on open — auto-set "Uang Pas" for instant Enter checkout
watch(() => props.visible, (val) => {
    if (val) {
        paymentMethod.value = 'cash';
        cashPaid.value = grandTotal.value; // Auto "Uang Pas"
        notes.value = '';
        showReceipt.value = false;
        receiptData.value = null;
        nextTick(() => {
            focusCashInput();
        });
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
                    <label class="font-semibold">Uang Diterima (Rp) — Enter = Konfirmasi</label>
                    <InputNumber ref="cashPaidInput" v-model="cashPaid" mode="currency" currency="IDR" locale="id-ID"
                        :min="0" class="w-full" inputClass="text-2xl font-bold text-center"
                        @keyup.enter="handleEnterCheckout" />
                </div>

                <!-- Quick amounts -->
                <div class="flex flex-wrap gap-2">
                    <Button v-for="amount in quickAmounts" :key="amount"
                        :label="amount === grandTotal ? `Uang Pas ${formatRp(amount)}` : formatRp(amount)"
                        size="small" outlined
                        :severity="cashPaid === amount ? 'primary' : 'secondary'"
                        @click="selectQuickAmount(amount)" />
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
                <InputText v-model="notes" placeholder="Catatan transaksi..." @keyup.enter="handleEnterCheckout" />
            </div>
        </div>

        <template #footer>
            <Button label="Batal" severity="secondary" text @click="emit('update:visible', false)" :disabled="submitting" />
            <Button label="Konfirmasi (Enter)" icon="pi pi-check" severity="success"
                :loading="submitting" :disabled="!isPaymentValid" @click="handleCheckout" />
        </template>
    </Dialog>

    <!-- Receipt Dialog -->
    <Dialog :visible="showReceipt" header="Struk Transaksi" modal :style="{ width: '420px' }" @update:visible="closeAll">
        <div v-if="receiptData" id="payment-receipt-print-area" class="font-mono text-sm">
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
            <p class="text-center text-xs mt-2 m-0">Terima Kasih Atas Kunjungan Anda</p>
            </div>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="closeAll" />
            <Button label="Cetak" icon="pi pi-print" @click="printReceipt" />
        </template>
    </Dialog>
</template>

<style scoped>
.print-spacer {
    display: none;
}
</style>
    <Dialog :visible="showReceipt" header="Struk Transaksi" modal :style="{ width: '420px' }" @update:visible="closeAll">
        <div v-if="receiptData" id="payment-receipt-print-area" class="font-mono text-sm">
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
            <p class="text-center text-xs mt-2 m-0">Terima Kasih Atas Kunjungan Anda</p>
            </div>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="closeAll" />
            <Button label="Cetak" icon="pi pi-print" @click="printReceipt" />
        </template>
    </Dialog>
</template>

<style scoped>
.print-spacer {
    display: none;
}
</style>
