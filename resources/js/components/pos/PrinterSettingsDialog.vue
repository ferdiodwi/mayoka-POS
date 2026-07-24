<script setup>
import { ref, watch } from 'vue';
import { useQzTray } from '@/composables/useQzTray';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible']);

const { getPrinters, printReceipt } = useQzTray();
const toast = useToast();

const printers = ref([]);
const selectedPrinter = ref(null);
const loading = ref(false);

const LOCAL_STORAGE_KEY = 'mayoka_selected_printer';

const fetchPrinters = async () => {
    loading.value = true;
    try {
        const list = await getPrinters();
        printers.value = list.map(p => ({ label: p, value: p }));
        
        // Cek printer tersimpan
        const saved = localStorage.getItem(LOCAL_STORAGE_KEY);
        if (saved && list.includes(saved)) {
            selectedPrinter.value = saved;
        }
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat daftar printer.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

watch(() => props.visible, (newVal) => {
    if (newVal) {
        fetchPrinters();
    }
});

const savePrinter = () => {
    if (selectedPrinter.value) {
        localStorage.setItem(LOCAL_STORAGE_KEY, selectedPrinter.value);
        toast.add({ severity: 'success', summary: 'Sukses', detail: `Printer default diatur ke ${selectedPrinter.value}`, life: 3000 });
    } else {
        localStorage.removeItem(LOCAL_STORAGE_KEY);
        toast.add({ severity: 'info', summary: 'Info', detail: 'Menggunakan deteksi printer otomatis.', life: 3000 });
    }
    emit('update:visible', false);
};

const testPrint = async () => {
    if (!selectedPrinter.value) {
        toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Pilih printer terlebih dahulu untuk test print.', life: 3000 });
        return;
    }
    
    // Simpan sementara agar test print bisa menggunakan printer ini
    localStorage.setItem(LOCAL_STORAGE_KEY, selectedPrinter.value);
    
    // Sample ESC/POS receipt for test
    // "Test Print" + line feed + cut
    const testData = "Test Print Berhasil\n\n\n\x1dV\x41\x00"; 
    const base64Data = btoa(testData);
    
    await printReceipt(base64Data);
};

const closeDialog = () => {
    emit('update:visible', false);
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="(val) => emit('update:visible', val)" 
        modal 
        header="Pengaturan Printer Kasir" 
        :style="{ width: '30vw' }"
        :breakpoints="{ '1199px': '50vw', '575px': '90vw' }"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm text-surface-500">
                Pilih printer khusus untuk mencetak struk pada komputer ini. Jika dikosongkan, sistem akan mencoba mencari printer kasir secara otomatis.
            </p>

            <div class="flex flex-col gap-2">
                <label for="printerSelect" class="font-bold">Daftar Printer Terhubung</label>
                <Dropdown 
                    id="printerSelect" 
                    v-model="selectedPrinter" 
                    :options="printers" 
                    optionLabel="label" 
                    optionValue="value" 
                    placeholder="Pilih Printer" 
                    class="w-full"
                    :loading="loading"
                    showClear
                />
            </div>

            <div class="flex justify-between items-center mt-4">
                <Button label="Test Print" icon="pi pi-print" severity="secondary" outlined @click="testPrint" :disabled="!selectedPrinter" />
                
                <div class="flex gap-2">
                    <Button label="Batal" icon="pi pi-times" text @click="closeDialog" />
                    <Button label="Simpan" icon="pi pi-check" @click="savePrinter" />
                </div>
            </div>
        </div>
    </Dialog>
</template>
