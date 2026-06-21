import { ref, computed } from 'vue';

const cartItems = ref([]);
const transactionDiscount = ref(0);

export function useCart() {

    /**
     * Add a print/fotokopi item to cart.
     */
    function addPrintItem({ paperSize, colorType, sideType, qty, unitPrice, costPerSheet, printPriceId, isCustom, addons }) {
        const colorLabel = colorType === 'bw' ? 'Hitam Putih' : 'Warna';
        const sideLabel = sideType === 'single' ? '1 Sisi' : 'Bolak-balik';
        const customLabel = isCustom ? ' (Kertas Sendiri/Custom)' : '';

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
            description: `${paperSize} — ${colorLabel} — ${sideLabel}${customLabel}`,
            qty,
            unitPrice: parseFloat(unitPrice),
            costPerSheet: parseFloat(costPerSheet),
            discount: 0,
            addons: itemAddons,
        });
    }

    /**
     * Add a product (ATK) item to cart.
     */
    function addProductItem(product, qty = 1) {
        // Check if same product already in cart
        const existing = cartItems.value.find(
            (i) => i.itemType === 'product' && i.productId === product.id
        );
        if (existing) {
            existing.qty += qty;
            applyWholesaleLogic(existing);
            return;
        }

        const itemToPush = {
            id: Date.now() + Math.random(),
            itemType: 'product',
            productId: product.id,
            description: product.name,
            qty,
            unitPrice: (product.units && product.units.length > 0) ? parseFloat(product.units[0].price_h1) : 0,
            costPrice: parseFloat(product.cost_price),
            discount: 0,
            stock: product.stock,
            units: product.units || [],
            unitName: (product.units && product.units.length > 0) ? product.units[0].unit_name : 'PCS',
            baseMultiplier: 1,
            priceTier: 'h1', // default H1
            barcode: product.barcode,
            barcode: product.barcode,
            addons: [],
        };
        
        applyWholesaleLogic(itemToPush);
        cartItems.value.push(itemToPush);
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
    };
}
