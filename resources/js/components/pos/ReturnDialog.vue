<script setup>
import { ref, computed, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { apiPost } from '@/composables/useApi';

const props = defineProps({
    visible: Boolean,
    transaction: Object
});

const emit = defineEmits(['update:visible', 'success']);
const toast = useToast();

const isSubmitting = ref(false);
const reason = ref('');

// Kami hanya menampilkan item bertipe 'product' yang masih bisa diretur (qty - returned_qty > 0)
const returnableItems = ref([]);

watch(() => props.visible, (val) => {
    if (val && props.transaction) {
        reason.value = '';
        returnableItems.value = (props.transaction.items || [])
            .filter(i => i.item_type === 'product' && (i.qty - (i.returned_qty || 0)) > 0)
            .map(i => ({
                ...i,
                return_qty: 0,
                max_return: i.qty - (i.returned_qty || 0)
            }));
    }
});

const totalRefund = computed(() => {
    return returnableItems.value.reduce((sum, item) => {
        return sum + (item.return_qty * item.unit_price);
    }, 0);
});

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function handleClose() {
    emit('update:visible', false);
}

async function processReturn() {
    const itemsToReturn = returnableItems.value.filter(i => i.return_qty > 0);
    if (itemsToReturn.length === 0) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Tidak ada barang yang dipilih untuk diretur.', life: 3000 });
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            reason: reason.value,
            items: itemsToReturn.map(i => ({
                id: i.id,
                return_qty: i.return_qty
            }))
        };

        const res = await apiPost(`/api/transactions/${props.transaction.id}/return`, payload);
        toast.add({ severity: 'success', summary: 'Berhasil', detail: res.message || 'Retur berhasil diproses.', life: 3000 });
        emit('success');
        handleClose();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Gagal memproses retur', life: 4000 });
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :visible="visible" modal header="Proses Retur Barang" :style="{ width: '35rem' }"
        @update:visible="handleClose">
        <div class="flex flex-col gap-4">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-sm">
                <i class="pi pi-info-circle mr-1"></i> Retur hanya berlaku untuk Barang ATK. Nominal refund akan dipotong dari <b>Kas Shift yang sedang aktif</b>.
            </div>

            <div v-if="returnableItems.length === 0" class="text-center p-4 text-muted-color">
                Tidak ada barang yang bisa diretur pada transaksi ini.
            </div>

            <div v-else class="flex flex-col gap-3">
                <div v-for="item in returnableItems" :key="item.id" 
                    class="p-3 border border-surface-200 dark:border-surface-700 rounded-lg flex items-center justify-between">
                    <div>
                        <div class="font-semibold">{{ item.description }}</div>
                        <div class="text-xs text-muted-color">{{ formatRp(item.unit_price) }} / item</div>
                        <div class="text-xs mt-1">Maks Retur: <b>{{ item.max_return }}</b></div>
                    </div>
                    <div class="w-32">
                        <InputNumber v-model="item.return_qty" :min="0" :max="item.max_return"
                            showButtons buttonLayout="horizontal"
                            incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus"
                            class="w-full" inputClass="text-center p-1 w-full" />
                    </div>
                </div>

                <div class="flex flex-col gap-1 mt-2">
                    <label class="text-sm font-semibold">Alasan Retur (Opsional)</label>
                    <Textarea v-model="reason" rows="2" class="w-full" placeholder="Misal: Barang cacat, salah beli..." />
                </div>

                <div class="mt-4 p-3 bg-surface-100 dark:bg-surface-800 rounded-lg flex justify-between items-center">
                    <span class="font-bold">Total Refund:</span>
                    <span class="text-xl font-bold text-red-500">-{{ formatRp(totalRefund) }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Batal" icon="pi pi-times" text severity="secondary" @click="handleClose" />
            <Button label="Proses Retur" icon="pi pi-check" severity="danger" 
                :disabled="totalRefund <= 0 || isSubmitting" :loading="isSubmitting" @click="processReturn" />
        </template>
    </Dialog>
</template>
