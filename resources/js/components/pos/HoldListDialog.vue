<script setup>
import { useHoldTransactions } from '@/composables/useHoldTransactions';

const props = defineProps({ visible: Boolean });
const emit = defineEmits(['update:visible', 'resume']);

const { holdList, removeHold } = useHoldTransactions();

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function calcTotal(items) {
    return items.reduce((sum, item) => {
        const base = item.qty * item.unitPrice;
        const addons = (item.addons || []).reduce((s, a) => s + a.price * a.qty, 0);
        return sum + base + addons;
    }, 0);
}

function handleResume(index) {
    emit('resume', index);
    emit('update:visible', false);
}

function handleRemove(index) {
    removeHold(index);
}
</script>

<template>
    <Dialog :visible="visible" @update:visible="emit('update:visible', $event)"
        header="Transaksi Ditahan" modal :style="{ width: '520px' }">

        <div v-if="holdList.length === 0" class="text-center py-8 text-muted-color">
            <i class="pi pi-inbox text-4xl mb-3 block"></i>
            <p class="m-0">Tidak ada transaksi yang ditahan.</p>
        </div>

        <div v-else class="flex flex-col gap-3">
            <div v-for="(held, index) in holdList" :key="held.id"
                class="p-4 rounded-lg border border-surface-200 dark:border-surface-700
                    hover:border-primary transition-colors cursor-pointer"
                @click="handleResume(index)">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="m-0 font-semibold">{{ held.label }}</p>
                        <p class="m-0 text-sm text-muted-color">
                            {{ held.items.length }} item · {{ formatTime(held.timestamp) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-bold text-primary">{{ formatRp(calcTotal(held.items)) }}</span>
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small"
                            @click.stop="handleRemove(index)" v-tooltip="'Hapus'" />
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" @click="emit('update:visible', false)" />
        </template>
    </Dialog>
</template>
