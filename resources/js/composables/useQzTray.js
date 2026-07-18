import qz from 'qz-tray';
import { useToast } from 'primevue/usetoast';

export function useQzTray() {
    const toast = useToast();

    /**
     * Print base64 ESC/POS data via QZ Tray
     * @param {string} base64Data 
     * @param {string} printerName (Optional, defaults to 'POS-80' or a .env value if configured)
     */
    const printReceipt = async (base64Data, printerName = 'POS-80') => {
        try {
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

            // Connect to QZ Tray if not already connected
            if (!qz.websocket.isActive()) {
                await qz.websocket.connect({ retries: 2, delay: 1 });
            }

            // Find the printer
            // Using true as second argument to search for exact match, or false for partial match
            let foundPrinter = null;
            try {
                foundPrinter = await qz.printers.find(printerName);
            } catch (findErr) {
                console.warn(`Printer ${printerName} not found, falling back to default printer`);
                foundPrinter = await qz.printers.getDefault();
            }

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
                detail: 'Struk berhasil dikirim ke printer kasir.', 
                life: 3000 
            });

        } catch (err) {
            console.error('QZ Tray Print Error:', err);
            
            let errorMsg = err.message || 'Pastikan aplikasi QZ Tray sudah berjalan di komputer ini.';
            if (err.message && err.message.includes('not found')) {
                errorMsg = `Printer dengan nama "${printerName}" tidak ditemukan di komputer ini.`;
            }

            toast.add({ 
                severity: 'error', 
                summary: 'Gagal Cetak', 
                detail: errorMsg, 
                life: 5000 
            });
        }
    };

    return {
        printReceipt
    };
}
