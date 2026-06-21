<script setup>
import { ref, watch, computed } from 'vue';
import { useShift } from '@/composables/useShift';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    visible: Boolean,
    mode: {
        type: String,
        default: 'open', // 'open' or 'close'
    },
});

const emit = defineEmits(['update:visible', 'shifted']);

const toast = useToast();
const { activeShift, openShift, closeShift } = useShift();

const cashStart = ref(0);
const cashEnd = ref(0);
const notes = ref('');
const submitting = ref(false);

const dialogTitle = computed(() =>
    props.mode === 'open' ? 'Buka Shift — Input Modal Awal' : 'Tutup Shift — Rekonsiliasi Kas'
);

const cashDifference = computed(() => {
    if (!activeShift.value) return 0;
    const expected = parseFloat(activeShift.value.live_expected_cash ?? activeShift.value.cash_start) || 0;
    return cashEnd.value - expected;
});

const differenceClass = computed(() => {
    if (cashDifference.value === 0) return 'text-green-500';
    if (cashDifference.value > 0) return 'text-blue-500';
    return 'text-red-500';
});

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
}

async function handleOpenShift() {
    if (cashStart.value < 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Modal awal tidak boleh negatif.', life: 3000 });
        return;
    }
    submitting.value = true;
    try {
        await openShift(cashStart.value);
        toast.add({ severity: 'success', summary: 'Shift Dibuka', detail: `Modal awal: ${formatCurrency(cashStart.value)}`, life: 3000 });
        emit('update:visible', false);
        emit('shifted', 'open');
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

async function handleCloseShift() {
    if (cashEnd.value < 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Uang akhir tidak boleh negatif.', life: 3000 });
        return;
    }
    submitting.value = true;
    try {
        const result = await closeShift(activeShift.value.id, cashEnd.value, notes.value || null);
        toast.add({ severity: 'success', summary: 'Shift Ditutup', detail: `Selisih kas: ${formatCurrency(result.shift.cash_difference)}`, life: 4000 });
        emit('update:visible', false);
        emit('shifted', 'close');
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message, life: 4000 });
    } finally {
        submitting.value = false;
    }
}

// Reset form when dialog opens
watch(() => props.visible, (val) => {
    if (val) {
        cashStart.value = 0;
        cashEnd.value = 0;
        notes.value = '';
    }
});
</script>

<template>
    <Dialog :visible="visible" @update:visible="emit('update:visible', $event)"
        :header="dialogTitle" modal :closable="mode !== 'open'" :style="{ width: '480px' }">

        <!-- Open Shift Mode -->
        <div v-if="mode === 'open'" class="flex flex-col gap-4 pt-2">
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="m-0 text-sm">
                    <i class="pi pi-info-circle mr-2"></i>
                    Masukkan jumlah uang tunai yang ada di laci kasir saat ini sebagai modal awal shift.
                </p>
            </div>
            <div class="flex flex-col gap-2">
                <label for="cash-start" class="font-semibold text-lg">Modal Awal (Rp)</label>
                <InputNumber id="cash-start" v-model="cashStart" mode="currency" currency="IDR" locale="id-ID"
                    :min="0" class="w-full" inputClass="text-2xl font-bold" autofocus />
            </div>
        </div>

        <!-- Close Shift Mode -->
        <div v-else class="flex flex-col gap-4 pt-2">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <p class="text-sm text-muted-color m-0">Modal Awal</p>
                    <p class="text-xl font-bold m-0 mt-1">{{ formatCurrency(activeShift?.cash_start || 0) }}</p>
                </div>
                <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <p class="text-sm text-muted-color m-0">Uang Seharusnya</p>
                    <p class="text-xl font-bold m-0 mt-1">{{ formatCurrency(activeShift?.live_expected_cash ?? activeShift?.cash_start ?? 0) }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="cash-end" class="font-semibold text-lg">Uang Fisik Akhir (Rp)</label>
                <InputNumber id="cash-end" v-model="cashEnd" mode="currency" currency="IDR" locale="id-ID"
                    :min="0" class="w-full" inputClass="text-2xl font-bold" autofocus />
            </div>

            <div class="p-4 rounded-lg" :class="cashDifference >= 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                <p class="text-sm text-muted-color m-0">Selisih Kas</p>
                <p class="text-2xl font-bold m-0 mt-1" :class="differenceClass">
                    {{ cashDifference >= 0 ? '+' : '' }}{{ formatCurrency(cashDifference) }}
                </p>
                <p v-if="cashDifference < 0" class="text-sm text-red-500 m-0 mt-1">
                    <i class="pi pi-exclamation-triangle mr-1"></i> Ada selisih minus!
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="shift-notes" class="font-semibold">Catatan (Opsional)</label>
                <Textarea id="shift-notes" v-model="notes" rows="2" placeholder="Catatan penutupan shift..." />
            </div>
        </div>

        <template #footer>
            <Button v-if="mode === 'close'" label="Batal" icon="pi pi-times" severity="secondary" text
                @click="emit('update:visible', false)" />
            <Button
                :label="mode === 'open' ? 'Buka Shift' : 'Tutup Shift'"
                :icon="mode === 'open' ? 'pi pi-play' : 'pi pi-stop'"
                :severity="mode === 'open' ? 'success' : 'danger'"
                :loading="submitting"
                @click="mode === 'open' ? handleOpenShift() : handleCloseShift()"
            />
        </template>
    </Dialog>
</template>
