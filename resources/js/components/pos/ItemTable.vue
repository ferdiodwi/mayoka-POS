<script setup>
import { computed } from 'vue';
import { useCart } from '@/composables/useCart';
import { formatRp } from '@/utils/format';

const emit = defineEmits(['select-row']);
const { cartItems, removeItem } = useCart();

const props = defineProps({
    selectedIndex: { type: Number, default: -1 },
});

function itemTotal(item) {
    const base = item.qty * item.unitPrice - (item.discount || 0);
    const addons = (item.addons || []).reduce((s, a) => s + a.price * (a.qty || 1), 0);
    return base + addons;
}
</script>

<template>
    <div class="flex flex-col h-full">
        <div class="overflow-auto flex-1">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-surface-100 dark:bg-surface-800 text-left font-semibold sticky top-0 z-10">
                        <th class="p-2 w-10 text-center">NO</th>
                        <th class="p-2 w-24">KODE</th>
                        <th class="p-2">NAMA</th>
                        <th class="p-2 w-20">SATUAN</th>
                        <th class="p-2 w-24 text-right">HARGA</th>
                        <th class="p-2 w-16 text-center">QTY</th>
                        <th class="p-2 w-24 text-right">DISC</th>
                        <th class="p-2 w-28 text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in cartItems" :key="item.id"
                        class="border-b border-surface-200 dark:border-surface-700 cursor-pointer transition-colors"
                        :class="i === selectedIndex ? 'bg-primary/15 font-semibold' : 'hover:bg-surface-50 dark:hover:bg-surface-800'"
                        @click="emit('select-row', i)">
                        <td class="p-2 text-center text-muted-color">{{ i + 1 }}</td>
                        <td class="p-2 font-mono text-xs">{{ item.barcode || '-' }}</td>
                        <td class="p-2">{{ item.description }}</td>
                        <td class="p-2">{{ item.unitName || '-' }}</td>
                        <td class="p-2 text-right">{{ formatRp(item.unitPrice) }}</td>
                        <td class="p-2 text-center font-bold">{{ item.qty }}</td>
                        <td class="p-2 text-right text-red-500">{{ item.discount > 0 ? formatRp(item.discount) : '-' }}</td>
                        <td class="p-2 text-right font-bold text-primary">{{ formatRp(itemTotal(item)) }}</td>
                    </tr>
                    <!-- Empty rows to fill space -->
                    <tr v-for="n in Math.max(0, 8 - cartItems.length)" :key="'empty-' + n"
                        class="border-b border-surface-200 dark:border-surface-700">
                        <td class="p-2 text-center text-muted-color">{{ cartItems.length + n }}</td>
                        <td class="p-2" colspan="7">&nbsp;</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
