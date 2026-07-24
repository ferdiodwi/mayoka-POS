import qz from 'qz-tray';
import { useToast } from 'primevue/usetoast';

export function useQzTray() {
    const toast = useToast();

    const LOCAL_STORAGE_KEY = 'mayoka_selected_printer';

    /**
     * Connect to QZ Tray and setup signatures
     */
    const initQz = async () => {
        // Setup QZ Tray Security Signatures
        qz.security.setCertificatePromise((resolve, reject) => {
            fetch('/api/qz-tray/cert')
                .then(res => {
                    if (!res.ok) throw new Error('Certificate endpoint returned error');
                    return res.text();
                })
                .then(resolve)
                .catch(reject);
        });

        qz.security.setSignaturePromise((toSign) => {
            return function(resolve, reject) {
                fetch('/api/qz-tray/sign?request=' + encodeURIComponent(toSign))
                    .then(res => {
                        if (!res.ok) throw new Error('Signature endpoint returned error');
                        return res.text();
                    })
                    .then(resolve)
                    .catch(reject);
            };
        });

        if (!qz.websocket.isActive()) {
            await qz.websocket.connect({ retries: 2, delay: 1 });
        }
    };

    /**
     * Get all available printers connected to this computer
     */
    const getPrinters = async () => {
        try {
            await initQz();
            const allPrinters = await qz.printers.find();
            return allPrinters;
        } catch (err) {
            console.error('Failed to get printers:', err);
            return [];
        }
    };

    /**
     * Print base64 ESC/POS data via QZ Tray
     * Prioritizes manually selected printer, then falls back to auto-detection.
     * @param {string} base64Data 
     */
    const printReceipt = async (base64Data) => {
        try {
            await initQz();


            // Get ALL installed printers, then pick the best match
            const allPrinters = await qz.printers.find();
            console.log('All available printers:', allPrinters);

            if (!allPrinters || allPrinters.length === 0) {
                throw new Error('Tidak ada printer yang terinstal di komputer ini.');
            }

            // Check for manually selected printer first
            const savedPrinter = localStorage.getItem(LOCAL_STORAGE_KEY);
            let foundPrinter = null;

            if (savedPrinter && allPrinters.includes(savedPrinter)) {
                foundPrinter = savedPrinter;
                console.log('Using manually selected printer:', foundPrinter);
            } else {
                // Try to find a POS/thermal printer by common naming patterns
                const posKeywords = ['POS-', 'POS_', 'EPSON', 'THERMAL', 'RECEIPT', 'XP-', 'RP-'];
                foundPrinter = allPrinters.find(p =>
                    posKeywords.some(keyword => p.toUpperCase().includes(keyword))
                );

                // If no POS-type printer found, just use the first available printer
                if (!foundPrinter) {
                    foundPrinter = allPrinters[0];
                }
                console.log('Auto-selected printer:', foundPrinter);
            }

            console.log('Selected printer:', foundPrinter);

            // Create config for the found printer
            const config = qz.configs.create(foundPrinter);

            // Print the raw base64 ESC/POS data
            const data = [{
                type: 'raw',
                format: 'base64',
                data: base64Data
            }];

            await qz.print(config, data);

            toast.add({
                severity: 'success',
                summary: 'Sukses',
                detail: `Struk berhasil dikirim ke ${foundPrinter}.`,
                life: 3000
            });

        } catch (err) {
            console.error('QZ Tray Print Error:', err);

            toast.add({
                severity: 'error',
                summary: 'Gagal Cetak',
                detail: err.message || 'Pastikan aplikasi QZ Tray sudah berjalan di komputer ini.',
                life: 5000
            });
        }
    };

    return {
        getPrinters,
        printReceipt
    };
}
