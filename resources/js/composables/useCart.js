import { ref, computed } from 'vue';

const cartItems = ref([]);
const transactionDiscount = ref(0);

export function useCart() {

    /**
     * Add a print/fotokopi item to cart.
     */
    function addPrintItem({ paperSize, colorType, sideType, qty, unitPrice, costPerSheet, printPriceId, isCustom, addons, discount = 0, notes = '' }) {
        const colorLabel = colorType === 'bw' ? 'Hitam Putih' : 'Warna';
        const sideLabel = sideType === 'single' ? '1 Sisi' : 'Bolak-balik';
        const customLabel = isCustom ? ' (Kertas Sendiri/Custom)' : '';
        let desc = `Print ${paperSize} — ${colorLabel} — ${sideLabel}${customLabel}`;
        if (notes) desc += ` (${notes})`;

        const itemAddons = (addons || []).map(addon => ({
            id: Date.now() + Math.random(),
            addonServiceId: addon.id,
            name: addon.name,
            price: parseFloat(addon.price),
            qty: 1,
        }));

        cartItems.value.push({
            id: Date.now() + Math.random(),
            itemType: 'print',
            printPriceId,
            description: desc,
            qty,
            unitPrice: parseFloat(unitPrice),
            costPerSheet: parseFloat(costPerSheet),
            discount: parseFloat(discount),
            addons: itemAddons,
        });
    }

    /**
     * Add a product (ATK) item to cart.
     * Supports both old (product, qty) and new inline entry (product, qty, unit, priceLevel, price, discount) signatures.
     */
    function addProductItem(product, qty = 1, unit = null, priceLevel = 'h1', price = null, discount = 0) {
        // Determine unit info
        const unitName = unit ? unit.unit_name : ((product.units && product.units.length > 0) ? product.units[0].unit_name : 'PCS');
        const unitLevel = unit ? unit.level : 1;
        const baseMultiplier = unit ? unit.base_multiplier : 1;
        const unitPrice = price !== null ? price : ((product.units && product.units.length > 0) ? parseFloat(product.units[0].price_h1) : 0);

        let actualProductId = product.id;
        if (typeof actualProductId === 'string' && (actualProductId.startsWith('addon-') || actualProductId.startsWith('custom-'))) {
            actualProductId = null;
        }

        // Check if same product + same unit already in cart
        const existing = cartItems.value.find(
            (i) => i.itemType === 'product' && 
                   ((i.productId !== null && i.productId === actualProductId) || 
                    (i.productId === null && i.description === product.name)) && 
                   i.unitLevel === unitLevel
        );
        if (existing) {
            existing.qty += qty;
            return;
        }

        cartItems.value.push({
            id: Date.now() + Math.random(),
            itemType: 'product',
            productId: actualProductId,
            description: product.name,
            qty,
            unitPrice,
            costPrice: parseFloat(product.cost_price || 0),
            discount: discount || 0,
            stock: product.stock,
            units: product.units || [],
            unitName,
            unitLevel,
            baseMultiplier,
            priceTier: priceLevel,
            productCode: product.product_code,
            barcode: product.barcode,
            addons: [],
        });
    }

    /**
     * Helper to apply wholesale price if conditions are met
     */
    function applyWholesaleLogic(item) {
        if (item.itemType !== 'product') return;
        // Wholesale logic is now replaced by manual H1/H2/H3 selection in the UI.
        // We will remove auto wholesale logic to avoid conflicting with the manual 3-tier price.
    }

    /**
     * Update price level for all products in the cart
     */
    function updatePriceLevelForCart(newPriceLevel) {
        cartItems.value.forEach(item => {
            if (item.itemType === 'product' && item.units && item.units.length > 0) {
                const unit = item.units.find(u => u.level === item.unitLevel);
                if (unit) {
                    item.priceTier = newPriceLevel;
                    if (newPriceLevel === 'h1') item.unitPrice = parseFloat(unit.price_h1 || 0);
                    else if (newPriceLevel === 'h2') item.unitPrice = parseFloat(unit.price_h2 || 0);
                    else if (newPriceLevel === 'h3') item.unitPrice = parseFloat(unit.price_h3 || 0);
                }
            }
        });
    }

    /**
     * Add an addon service to a specific cart item (usually print item).
     */
    function addAddonToItem(itemIndex, addon) {
        const item = cartItems.value[itemIndex];
        if (!item) return;

        // Check if addon already added
        const exists = item.addons.find((a) => a.addonServiceId === addon.id);
        if (exists) return;

        item.addons.push({
            id: Date.now() + Math.random(),
            addonServiceId: addon.id,
            name: addon.name,
            price: parseFloat(addon.price),
            qty: 1,
        });
    }

    /**
     * Remove addon from item.
     */
    function removeAddonFromItem(itemIndex, addonIndex) {
        const item = cartItems.value[itemIndex];
        if (item) {
            item.addons.splice(addonIndex, 1);
        }
    }

    /**
     * Update qty of a cart item.
     */
    function updateItemQty(index, qty) {
        if (qty <= 0) {
            removeItem(index);
            return;
        }
        cartItems.value[index].qty = qty;
        applyWholesaleLogic(cartItems.value[index]);
    }

    /**
     * Remove item from cart (addons go with it).
     */
    function removeItem(index) {
        cartItems.value.splice(index, 1);
    }

    /**
     * Apply discount to a specific item.
     */
    function applyItemDiscount(index, discount) {
        cartItems.value[index].discount = discount;
    }

    /**
     * Set transaction-level discount.
     */
    function setTransactionDiscount(amount) {
        transactionDiscount.value = amount;
    }

    /**
     * Clear entire cart.
     */
    function clearCart() {
        cartItems.value = [];
        transactionDiscount.value = 0;
    }

    // Computed totals
    const subtotal = computed(() => {
        return cartItems.value.reduce((sum, item) => {
            const itemTotal = item.qty * item.unitPrice - item.discount;
            const addonsTotal = item.addons.reduce((s, a) => s + a.price * a.qty, 0);
            return sum + itemTotal + addonsTotal;
        }, 0);
    });

    const totalDiscount = computed(() => {
        const itemDiscounts = cartItems.value.reduce((sum, item) => sum + item.discount, 0);
        return itemDiscounts + transactionDiscount.value;
    });

    const grandTotal = computed(() => {
        return subtotal.value - transactionDiscount.value;
    });

    const cartCount = computed(() => cartItems.value.length);

    const isEmpty = computed(() => cartItems.value.length === 0);

    return {
        cartItems,
        transactionDiscount,
        addPrintItem,
        addProductItem,
        addAddonToItem,
        removeAddonFromItem,
        updateItemQty,
        removeItem,
        applyItemDiscount,
        setTransactionDiscount,
        clearCart,
        subtotal,
        totalDiscount,
        grandTotal,
        cartCount,
        isEmpty,
        updatePriceLevelForCart,
    };
}
