<script setup>
import { ref, computed, watch } from 'vue';
import { usePosData } from '@/composables/usePosData';

const emit = defineEmits(['add']);
const { calculatePrintPrice, addonServices } = usePosData();

const paperSize = ref('A4');
const colorType = ref('bw');
const sideType = ref('single');
const qty = ref(1);
const selectedAddons = ref([]);

const paperOptions = [{ label: 'A4', value: 'A4' }, { label: 'F4', value: 'F4' }, { label: 'A3', value: 'A3' }];
const colorOptions = [{ label: 'Hitam Putih', value: 'bw' }, { label: 'Warna', value: 'color' }];
const sideOptions = [{ label: '1 Sisi', value: 'single' }, { label: 'Bolak-balik', value: 'duplex' }];

const priceCalc = computed(() => {
    return calculatePrintPrice(paperSize.value, colorType.value, sideType.value, qty.value);
});

const displayPrice = computed(() => {
    return customPriceEnabled.value ? customPrice.value : (priceCalc.value?.effectivePrice || 0);
});

const displaySubtotal = computed(() => {
    return displayPrice.value * qty.value;
});

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

const customPriceEnabled = ref(false);
const customPrice = ref(0);

watch(priceCalc, (newVal) => {
    if (newVal && !customPriceEnabled.value) {
        customPrice.value = newVal.effectivePrice;
    }
}, { immediate: true });

watch(customPriceEnabled, (val) => {
    if (val && priceCalc.value) {
        customPrice.value = Math.max(0, priceCalc.value.effectivePrice - 100); // Default pengurangan/harga usulan
    }
});

function handleAdd() {
    if (!priceCalc.value || qty.value < 1) return;

    emit('add', {
        paperSize: paperSize.value,
        colorType: colorType.value,
        sideType: sideType.value,
        qty: qty.value,
        unitPrice: customPriceEnabled.value ? customPrice.value : priceCalc.value.effectivePrice,
        costPerSheet: customPriceEnabled.value ? 0 : priceCalc.value.costPerSheet,
        printPriceId: priceCalc.value.printPriceId,
        isCustom: customPriceEnabled.value,
        addons: selectedAddons.value
    });

    qty.value = 1;
    customPriceEnabled.value = false;
    selectedAddons.value = [];
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

        <div class="grid grid-cols-1 gap-4">
            <div class="flex flex-col gap-1 w-1/2">
                <label class="text-sm font-semibold text-muted-color">Jumlah Lembar</label>
                <InputNumber v-model="qty" :min="1" showButtons buttonLayout="horizontal"
                    incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus"
                    class="w-full" inputClass="text-center text-xl font-bold" />
            </div>

            <!-- Addons Selection -->
            <div v-if="addonServices && addonServices.length > 0" class="flex flex-col gap-2 border-t border-surface-200 dark:border-surface-700 pt-3">
                <label class="text-sm font-semibold text-muted-color">Tambahan Jasa</label>
                <div class="grid grid-cols-2 gap-2">
                    <div v-for="addon in addonServices" :key="addon.id" class="flex items-center gap-2">
                        <Checkbox v-model="selectedAddons" :inputId="'addon'+addon.id" name="addon" :value="addon" />
                        <label :for="'addon'+addon.id" class="text-sm cursor-pointer select-none">
                            {{ addon.name }} <span class="text-primary font-semibold">(+{{ formatRp(addon.price) }})</span>
                        </label>
                    </div>
                </div>
            </div>

            <Button label="Tambah ke Keranjang" icon="pi pi-cart-plus" class="h-12 w-full mt-2"
                :disabled="!priceCalc || qty < 1" @click="handleAdd" />
        </div>

        <!-- Custom Price Toggle -->
        <div class="flex items-center gap-3 p-3 bg-surface-50 dark:bg-surface-900 rounded-lg border border-surface-200 dark:border-surface-700">
            <div class="flex items-center gap-2">
                <ToggleSwitch v-model="customPriceEnabled" />
                <label class="text-sm font-semibold cursor-pointer select-none" @click="customPriceEnabled = !customPriceEnabled">
                    Kertas Bawa Sendiri / Harga Custom
                </label>
            </div>
            
            <div v-if="customPriceEnabled" class="flex-1">
                <InputNumber v-model="customPrice" mode="currency" currency="IDR" locale="id-ID" :min="0"
                    class="w-full" inputClass="p-2 text-sm" placeholder="Harga per lembar" />
            </div>
        </div>

        <!-- Price Preview -->
        <div v-if="priceCalc" class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm text-muted-color">Harga per lembar:</span>
                    <span class="ml-2 font-semibold">{{ formatRp(displayPrice) }}</span>
                    <Tag v-if="priceCalc.isTierApplied && !customPriceEnabled" value="Harga Grosir" severity="success" class="ml-2" />
                    <Tag v-if="customPriceEnabled" value="Harga Custom" severity="info" class="ml-2" />
                </div>
                <div class="text-right">
                    <span class="text-sm text-muted-color">Subtotal:</span>
                    <span class="ml-2 text-xl font-bold text-primary">{{ formatRp(displaySubtotal) }}</span>
                </div>
            </div>
            <div v-if="priceCalc.isTierApplied && !customPriceEnabled" class="mt-2 text-sm text-green-600 dark:text-green-400">
                <i class="pi pi-check-circle mr-1"></i>
                Harga normal {{ formatRp(priceCalc.normalPrice) }}/lbr → Grosir {{ formatRp(priceCalc.effectivePrice) }}/lbr
            </div>
            <div v-if="priceCalc.tiers.length > 0 && !customPriceEnabled" class="mt-2 text-xs text-muted-color">
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
