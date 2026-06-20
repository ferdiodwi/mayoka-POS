<script setup>
import { ref, watch } from 'vue';
import { usePosData } from '@/composables/usePosData';

const emit = defineEmits(['add']);
const { searchProducts } = usePosData();

const query = ref('');
const results = ref([]);
const searching = ref(false);
const searchInputRef = ref(null);

let debounceTimer = null;

watch(query, (val) => {
    clearTimeout(debounceTimer);
    if (!val || val.length < 1) {
        results.value = [];
        return;
    }
    debounceTimer = setTimeout(async () => {
        searching.value = true;
        try {
            results.value = await searchProducts(val);
        } finally {
            searching.value = false;
        }
    }, 300);
});

function selectProduct(product) {
    emit('add', product, 1);
    query.value = '';
    results.value = [];
    // Re-focus search input
    setTimeout(() => searchInputRef.value?.$el?.querySelector('input')?.focus(), 100);
}

function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}

function focusInput() {
    searchInputRef.value?.$el?.querySelector('input')?.focus();
}

defineExpose({ focusInput });
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="relative">
            <IconField>
                <InputIcon class="pi pi-search" />
                <InputText ref="searchInputRef" v-model="query"
                    placeholder="Cari nama produk atau scan barcode..."
                    class="w-full" />
            </IconField>
        </div>

        <div v-if="searching" class="text-center p-4 text-muted-color">
            <i class="pi pi-spin pi-spinner mr-2"></i> Mencari...
        </div>

        <div v-else-if="results.length > 0" class="flex flex-col gap-1 max-h-80 overflow-y-auto">
            <div v-for="product in results" :key="product.id"
                class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors
                    hover:bg-primary/10 border border-surface-200 dark:border-surface-700"
                :class="{ 'opacity-50': product.type === 'barang' && product.stock <= 0 }"
                @click="product.type !== 'barang' || product.stock > 0 ? selectProduct(product) : null">
                <div>
                    <p class="m-0 font-semibold">{{ product.name }}</p>
                    <p class="m-0 text-sm text-muted-color">
                        {{ product.category?.name }}
                        <span v-if="product.barcode"> · {{ product.barcode }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="m-0 font-bold text-primary">{{ formatRp(product.price) }}</p>
                    <p v-if="product.type === 'barang'" class="m-0 text-sm"
                        :class="product.stock <= 0 ? 'text-red-500' : 'text-muted-color'">
                        {{ product.stock <= 0 ? 'Habis' : `Stok: ${product.stock} ${product.unit}` }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else-if="query.length > 0 && !searching" class="text-center p-4 text-muted-color">
            <i class="pi pi-search mr-1"></i> Produk tidak ditemukan.
        </div>

        <div v-else class="text-center p-8 text-muted-color">
            <i class="pi pi-barcode text-4xl mb-3 block"></i>
            <p class="m-0">Ketik nama produk atau scan barcode untuk mencari.</p>
        </div>
    </div>
</template>
