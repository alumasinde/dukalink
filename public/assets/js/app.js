document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach((element) => {
        setTimeout(() => element.remove(), 4000);
    });

    const store = window.DUKAME_STORE;
    const cartKey = store ? `dukame_cart_${store.slug}` : null;

    if (store && cartKey) {
        const getCart = () => JSON.parse(localStorage.getItem(cartKey) || '[]');
        const saveCart = (cart) => localStorage.setItem(cartKey, JSON.stringify(cart));

        const updateCartUI = () => {
            const count = getCart().reduce((sum, item) => sum + item.quantity, 0);
            document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = count);

            const floating = document.querySelector('[data-floating-cart]');
            if (floating) floating.hidden = count === 0;
        };

        document.querySelectorAll('[data-add-cart]').forEach(button => {
            button.addEventListener('click', () => {
                const cart = getCart();
                const id = Number(button.dataset.productId);
                const existing = cart.find(item => item.id === id);

                if (existing) {
                    existing.quantity += 1;
                } else {
                    cart.push({
                        id,
                        name: button.dataset.productName,
                        price: Number(button.dataset.productPrice),
                        quantity: 1
                    });
                }

                saveCart(cart);
                updateCartUI();

                const original = button.textContent;
                button.textContent = '✓';
                setTimeout(() => button.textContent = original, 650);
            });
        });

        const search = document.querySelector('[data-store-search]');
        if (search) {
            search.addEventListener('input', () => {
                const query = search.value.toLowerCase().trim();
                document.querySelectorAll('.store-product').forEach(card => {
                    card.hidden = query !== '' && !card.dataset.name.includes(query);
                });
            });
        }

        updateCartUI();
    }

    const productSearch = document.querySelector('[data-product-search]');
    if (productSearch) {
        productSearch.addEventListener('input', () => {
            const query = productSearch.value.toLowerCase().trim();
            document.querySelectorAll('.product-list-item').forEach(card => {
                card.hidden = query !== '' && !card.dataset.productName.includes(query);
            });
        });
    }
});


document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async () => {
        const text = button.getAttribute('data-copy-text') || '';
        try {
            const absolute = new URL(text, window.location.origin).href;
            await navigator.clipboard.writeText(absolute);
            const original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = original; }, 1400);
        } catch (_) {
            // Clipboard access may be unavailable on insecure local pages.
        }
    });
});
