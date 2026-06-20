<script setup>
import { ref, computed, watch } from 'vue';
import { usePosData } from '@/composables/usePosData';

const emit = defineEmits(['add']);
const { calculatePrintPrice } = usePosData();

const paperSize = ref('A4');
const colorType = ref('bw');
const sideType = ref('single');
const qty = ref(1);

const paperOptions = [{ label: 'A4', value: 'A4' }, { label: 'F4', value: 'F4' }, { label: 'A3', value: 'A3' }];
const colorOptions = [{ label: 'Hitam Putih', value: 'bw' }, { label: 'Warna', value: 'color' }];
const sideOptions = [{ label: '1 Sisi', value: 'single' }, { label: 'Bolak-balik', value: 'duplex' }];

const priceCalc = computed(() => {
    return calculatePrintPrice(paperSize.value, colorType.value, sideType.value, qty.value);
});

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function handleAdd() {
    if (!priceCalc.value || qty.value < 1) return;

    emit('add', {
        paperSize: paperSize.value,
        colorType: colorType.value,
        sideType: sideType.value,
        qty: qty.value,
        unitPrice: priceCalc.value.effectivePrice,
        costPerSheet: priceCalc.value.costPerSheet,
        printPriceId: priceCalc.value.printPriceId,
    });

    qty.value = 1;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-3 gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Ukuran Kertas</label>
                <SelectButton v-model="paperSize" :options="paperOptions" optionLabel="label" optionValue="value" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Jenis Tinta</label>
                <SelectButton v-model="colorType" :options="colorOptions" optionLabel="label" optionValue="value" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Sisi Cetak</label>
                <SelectButton v-model="sideType" :options="sideOptions" optionLabel="label" optionValue="value" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-muted-color">Jumlah Lembar</label>
                <InputNumber v-model="qty" :min="1" showButtons buttonLayout="horizontal"
                    incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus"
                    class="w-full" inputClass="text-center text-xl font-bold" />
            </div>
            <Button label="Tambah ke Keranjang" icon="pi pi-cart-plus" class="h-12"
                :disabled="!priceCalc || qty < 1" @click="handleAdd" />
        </div>

        <!-- Price Preview -->
        <div v-if="priceCalc" class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm text-muted-color">Harga per lembar:</span>
                    <span class="ml-2 font-semibold">{{ formatRp(priceCalc.effectivePrice) }}</span>
                    <Tag v-if="priceCalc.isTierApplied" value="Harga Grosir" severity="success" class="ml-2" />
                </div>
                <div class="text-right">
                    <span class="text-sm text-muted-color">Subtotal:</span>
                    <span class="ml-2 text-xl font-bold text-primary">{{ formatRp(priceCalc.subtotal) }}</span>
                </div>
            </div>
            <div v-if="priceCalc.isTierApplied" class="mt-2 text-sm text-green-600 dark:text-green-400">
                <i class="pi pi-check-circle mr-1"></i>
                Harga normal {{ formatRp(priceCalc.normalPrice) }}/lbr → Grosir {{ formatRp(priceCalc.effectivePrice) }}/lbr
            </div>
            <div v-if="priceCalc.tiers.length > 0" class="mt-2 text-xs text-muted-color">
                Tier: <span v-for="(t, i) in priceCalc.tiers" :key="i">
                    ≥{{ t.min_qty }} lbr = {{ formatRp(t.price_per_sheet) }}{{ i < priceCalc.tiers.length - 1 ? ' | ' : '' }}
                </span>
            </div>
        </div>
        <div v-else class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm text-red-500">
            <i class="pi pi-info-circle mr-1"></i> Kombinasi harga belum tersedia.
        </div>
    </div>
</template>
