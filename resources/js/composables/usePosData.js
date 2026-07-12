import { ref } from 'vue';
import { apiGet } from '@/composables/useApi';

const printPrices = ref([]);
const addonServices = ref([]);
const products = ref([]);
const productsByBarcode = new Map();
const loaded = ref(false);

export function usePosData() {

    async function loadPosData() {
        if (loaded.value) return;
        try {
            const [ppRes, addonRes, prodRes] = await Promise.all([
                apiGet('/api/print-prices'),
                apiGet('/api/addon-services'),
                apiGet('/api/products/catalog'),
            ]);
            printPrices.value = ppRes.print_prices;
            addonServices.value = addonRes.addon_services.filter(a => a.is_active);
            products.value = prodRes.products;
            buildProductIndex();
            loaded.value = true;
        } catch (e) {
            console.error('Failed to load POS data:', e);
        }
    }

    function buildProductIndex() {
        productsByBarcode.clear();
        products.value.forEach(p => {
            if (p.barcode) {
                productsByBarcode.set(p.barcode, p);
            }
        });
    }

    /**
     * Refresh products cache (e.g., after stock changes).
     */
    async function refreshProducts() {
        try {
            const prodRes = await apiGet('/api/products/catalog');
            products.value = prodRes.products;
            buildProductIndex();
        } catch (e) {
            console.error('Failed to refresh products:', e);
        }
    }

    /**
     * Update a single product's stock in the local cache.
     */
    function updateProductStock(productId, newStock) {
        const product = products.value.find(p => p.id === productId);
        if (product) {
            product.stock = newStock;
        }
    }

    /**
     * Calculate print price from cached data (no API call needed).
     */
    function calculatePrintPrice(paperSize, colorType, sideType, qty) {
        const pp = printPrices.value.find(
            (p) => p.paper_size === paperSize && p.color_type === colorType && p.side_type === sideType
        );
        if (!pp) return null;

        // Find best tier
        let effectivePrice = parseFloat(pp.price_per_sheet);
        if (pp.tiers && pp.tiers.length > 0) {
            const applicableTiers = pp.tiers
                .filter((t) => qty >= t.min_qty)
                .sort((a, b) => b.min_qty - a.min_qty);
            if (applicableTiers.length > 0) {
                effectivePrice = parseFloat(applicableTiers[0].price_per_sheet);
            }
        }

        return {
            printPriceId: pp.id,
            normalPrice: parseFloat(pp.price_per_sheet),
            effectivePrice,
            costPerSheet: parseFloat(pp.cost_per_sheet),
            subtotal: effectivePrice * qty,
            isTierApplied: effectivePrice < parseFloat(pp.price_per_sheet),
            tiers: pp.tiers || [],
        };
    }

    /**
     * Search products from local cache — instant, no API call.
     */
    function searchProducts(query) {
        if (!query || query.length < 1) return [];

        // Exact barcode match — instant
        const barcodeMatch = productsByBarcode.get(query);
        if (barcodeMatch) return [barcodeMatch];

        // Name/barcode partial search
        const q = query.toLowerCase();
        let results = products.value.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.barcode && p.barcode.includes(query))
        );

        if (printPrices.value.length > 0) {
            const printResults = printPrices.value.filter(pp => {
                const label = `print cetak fotokopi ${pp.paper_size} ${pp.color_type === 'bw' ? 'hitam putih bw' : 'warna color'} ${pp.side_type === 'single' ? '1 sisi single' : 'bolak-balik duplex'}`.toLowerCase();
                return q.split(' ').every(term => label.includes(term));
            }).map(pp => {
                const label = `Print ${pp.paper_size} ${pp.color_type === 'bw' ? 'Hitam Putih' : 'Warna'} ${pp.side_type === 'single' ? '1 Sisi' : 'Bolak-balik'}`;
                return {
                    id: `print-${pp.id}`,
                    printPriceId: pp.id,
                    name: label,
                    type: 'print',
                    stock: 0, // No stock limit
                    cost_price: pp.cost_per_sheet,
                    units: [{ level: 1, unit_name: 'LBR', base_multiplier: 1, price_h1: pp.price_per_sheet }],
                    barcode: null,
                    pp: pp // Raw data for tier calculation
                };
            });
            results = [...printResults, ...results];
        }

        if (addonServices.value.length > 0) {
            const addonResults = addonServices.value.filter(a => {
                const label = `tambahan jasa addon ${a.name}`.toLowerCase();
                return q.split(' ').every(term => label.includes(term));
            }).map(a => {
                return {
                    id: `addon-${a.id}`,
                    name: `Tambahan: ${a.name}`,
                    type: 'jasa',
                    stock: 0,
                    cost_price: 0,
                    units: [{ level: 1, unit_name: 'PCS', base_multiplier: 1, price_h1: a.price }],
                    barcode: null
                };
            });
            results = [...addonResults, ...results];
        }

        return results.slice(0, 20);
    }

    /**
     * Get product by exact barcode (instant).
     */
    function getProductByBarcode(barcode) {
        return productsByBarcode.get(barcode) || null;
    }

    return {
        printPrices,
        addonServices,
        products,
        loaded,
        loadPosData,
        refreshProducts,
        updateProductStock,
        calculatePrintPrice,
        searchProducts,
        getProductByBarcode,
    };
}
