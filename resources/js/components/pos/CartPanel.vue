<script setup>
import { useCart } from '@/composables/useCart';
import { useConfirm } from 'primevue/useconfirm';

const emit = defineEmits(['pay', 'hold']);
const confirm = useConfirm();
const { cartItems, updateItemQty, removeItem, removeAddonFromItem, subtotal, grandTotal, isEmpty, clearCart } = useCart();

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function itemSubtotal(item) {
    const base = item.qty * item.unitPrice - item.discount;
    const addons = item.addons.reduce((s, a) => s + a.price * a.qty, 0);
    return base + addons;
}

function confirmClear() {
    confirm.require({
        message: 'Kosongkan seluruh keranjang?',
        header: 'Konfirmasi',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Batal',
        acceptLabel: 'Kosongkan',
        acceptClass: 'p-button-danger',
        accept: () => clearCart(),
    });
}
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
            <h3 class="m-0 text-lg font-semibold">
                <i class="pi pi-shopping-cart mr-2"></i> Keranjang
                <Tag v-if="!isEmpty" :value="`${cartItems.length} item`" severity="info" class="ml-2" />
            </h3>
            <Button v-if="!isEmpty" icon="pi pi-trash" severity="danger" text rounded size="small"
                v-tooltip="'Kosongkan'" @click="confirmClear" />
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto min-h-[300px] lg:min-h-0 lg:max-h-[calc(100vh-380px)]">
            <div v-if="isEmpty" class="text-center py-12 text-muted-color">
                <i class="pi pi-shopping-cart text-5xl mb-3 block opacity-30"></i>
                <p class="m-0">Keranjang masih kosong</p>
                <p class="m-0 text-sm">Tambahkan item dari panel kiri</p>
            </div>

            <div v-else class="flex flex-col gap-2">
                <div v-for="(item, index) in cartItems" :key="item.id"
                    class="p-3 rounded-lg border border-surface-200 dark:border-surface-700">
                    <!-- Main item -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <Tag :value="item.itemType === 'print' ? 'Cetak' : 'Barang'" size="small"
                                    :severity="item.itemType === 'print' ? 'warn' : 'info'" />
                                <span class="font-semibold text-sm">{{ item.description }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <InputNumber v-model="item.qty" :min="1" showButtons
                                    buttonLayout="horizontal" size="small"
                                    incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus"
                                    class="w-28" inputClass="text-center text-sm p-1"
                                    @update:modelValue="(val) => updateItemQty(index, val)" />
                                <span class="text-sm text-muted-color">× {{ formatRp(item.unitPrice) }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="m-0 font-bold">{{ formatRp(itemSubtotal(item)) }}</p>
                            <Button icon="pi pi-times" severity="danger" text rounded size="small"
                                @click="removeItem(index)" />
                        </div>
                    </div>

                    <!-- Addons -->
                    <div v-if="item.addons.length > 0" class="mt-2 ml-4 border-l-2 border-surface-200 dark:border-surface-600 pl-3">
                        <div v-for="(addon, ai) in item.addons" :key="addon.id"
                            class="flex items-center justify-between py-1">
                            <span class="text-sm text-muted-color">
                                <i class="pi pi-plus text-xs mr-1"></i> {{ addon.name }}
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm">{{ formatRp(addon.price) }}</span>
                                <Button icon="pi pi-times" severity="secondary" text rounded size="small"
                                    class="w-6 h-6" @click="removeAddonFromItem(index, ai)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="mt-auto pt-4 border-t border-surface-200 dark:border-surface-700">
            <div class="flex justify-between mb-2">
                <span class="text-muted-color">Subtotal</span>
                <span class="font-semibold">{{ formatRp(subtotal) }}</span>
            </div>
            <div class="flex justify-between mb-4">
                <span class="text-xl font-bold">TOTAL</span>
                <span class="text-2xl font-bold text-primary">{{ formatRp(grandTotal) }}</span>
            </div>

            <div class="flex gap-2">
                <Button label="Hold (Alt+H)" icon="pi pi-pause" severity="warn" outlined class="flex-1"
                    :disabled="isEmpty" @click="emit('hold')" />
                <Button label="Bayar (Alt+B)" icon="pi pi-money-bill" severity="success" class="flex-1 text-lg font-bold"
                    :disabled="isEmpty" @click="emit('pay')" />
            </div>
        </div>
    </div>

    <ConfirmDialog />
</template>
