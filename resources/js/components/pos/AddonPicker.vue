<script setup>
import { usePosData } from '@/composables/usePosData';

const props = defineProps({
    cartItems: { type: Array, required: true },
});

const emit = defineEmits(['add']);
const { addonServices } = usePosData();

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

// Only print items can have addons
const printItemsInCart = () => props.cartItems.filter((i) => i.itemType === 'print');

function addAddon(addon, itemIndex) {
    emit('add', itemIndex, addon);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-if="printItemsInCart().length === 0"
            class="text-center p-6 text-muted-color">
            <i class="pi pi-info-circle text-2xl mb-2 block"></i>
            <p class="m-0 text-sm">Tambahkan item cetak ke keranjang terlebih dahulu untuk menambahkan addon.</p>
        </div>

        <template v-else>
            <p class="text-sm text-muted-color m-0">Pilih addon, lalu pilih item cetak tujuan:</p>

            <div class="grid grid-cols-2 gap-2">
                <div v-for="addon in addonServices" :key="addon.id"
                    class="border border-surface-200 dark:border-surface-700 rounded-lg p-3">
                    <p class="m-0 font-semibold text-sm">{{ addon.name }}</p>
                    <p class="m-0 text-primary font-bold">{{ formatRp(addon.price) }}</p>
                    <div class="mt-2 flex flex-col gap-1">
                        <Button v-for="(item, idx) in cartItems" :key="item.id"
                            v-show="item.itemType === 'print'"
                            :label="`+ ${item.description}`"
                            size="small" outlined severity="secondary" class="text-xs justify-start"
                            @click="addAddon(addon, idx)" />
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
