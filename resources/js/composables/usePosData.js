import { ref } from 'vue';
import { apiGet } from '@/composables/useApi';

const printPrices = ref([]);
const addonServices = ref([]);
const loaded = ref(false);

export function usePosData() {

    async function loadPosData() {
        if (loaded.value) return;
        try {
            const [ppRes, addonRes] = await Promise.all([
                apiGet('/api/print-prices'),
                apiGet('/api/addon-services'),
            ]);
            printPrices.value = ppRes.print_prices;
            addonServices.value = addonRes.addon_services.filter(a => a.is_active);
            loaded.value = true;
        } catch (e) {
            console.error('Failed to load POS data:', e);
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
     * Search products via API.
     */
    async function searchProducts(query) {
        if (!query || query.length < 1) return [];
        const data = await apiGet(`/api/products/search?q=${encodeURIComponent(query)}`);
        return data.products;
    }

    return {
        printPrices,
        addonServices,
        loaded,
        loadPosData,
        calculatePrintPrice,
        searchProducts,
    };
}
