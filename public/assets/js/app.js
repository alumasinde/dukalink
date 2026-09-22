document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach((element) => setTimeout(() => element.remove(), 4000));

    const store = window.DUKAME_STORE;
    const cartKey = store?.slug ? `dukame_cart_${store.slug}` : null;
    const money = (value) => `${store?.currency || 'KES'} ${Number(value).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const getCart = () => cartKey ? JSON.parse(localStorage.getItem(cartKey) || '[]') : [];
    const saveCart = (cart) => { if (cartKey) localStorage.setItem(cartKey, JSON.stringify(cart)); };
    const setActiveStore = () => { if (store?.slug) { localStorage.setItem('dukame_active_store', store.slug); localStorage.setItem('dukame_active_currency', store.currency || 'KES'); } };
    setActiveStore();

    const updateCartUI = () => {
        const cart = getCart();
        const count = cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        const total = cart.reduce((sum, item) => sum + Number(item.price || 0) * Number(item.quantity || 0), 0);
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = count);
        document.querySelectorAll('[data-cart-total]').forEach(el => el.textContent = money(total));
        document.querySelectorAll('[data-cart-button]').forEach(el => el.classList.toggle('has-items', count > 0));
        document.querySelectorAll('[data-floating-cart]').forEach(el => el.hidden = count === 0);
    };

    const flashAdded = (button) => {
        const original = button.textContent;
        button.textContent = '✓'; button.classList.add('added');
        setTimeout(() => { button.textContent = original; button.classList.remove('added'); }, 700);
    };

    const addItem = (data, quantity = 1, options = []) => {
        if (!cartKey) return false;
        const trackInventory = data.trackInventory === true || data.trackInventory === '1';
        const stock = Math.max(0, Number(data.stock ?? 0));
        if (trackInventory && stock < 1) return false;
        const cart = getCart();
        const normalizedOptions = [...options].sort();
        const key = `${data.id}:${normalizedOptions.join('|')}`;
        const existing = cart.find(item => item.key === key);
        if (existing) {
            const nextQuantity = Number(existing.quantity || 0) + quantity;
            if (trackInventory && nextQuantity > stock) return false;
            existing.quantity = nextQuantity;
            existing.stock = stock;
            existing.trackInventory = trackInventory;
        } else {
            if (trackInventory && quantity > stock) quantity = stock;
            cart.push({key, id: Number(data.id), name: data.name, price: Number(data.price), image: data.image || '', slug: data.slug || '', quantity, options: normalizedOptions, stock, trackInventory});
        }
        saveCart(cart); updateCartUI();
        return true;
    };

    document.querySelectorAll('[data-add-cart]').forEach(button => button.addEventListener('click', () => {
        const added = addItem({id:button.dataset.productId,name:button.dataset.productName,price:button.dataset.productPrice,image:button.dataset.productImage,slug:button.dataset.productSlug,stock:button.dataset.productStock,trackInventory:button.dataset.trackInventory});
        if (added) flashAdded(button);
    }));

    // Storefront product-card controls: quantity + variations without leaving the catalogue.
    const cardOptions = (card) => [...card.querySelectorAll('[data-card-option].selected')].map(el => el.value);
    const cardKey = (card) => {
        const options = [...cardOptions(card)].sort();
        return `${card.dataset.productId}:${options.join('|')}`;
    };
    const refreshCard = (card) => {
        const options = cardOptions(card);
        const key = `${card.dataset.productId}:${[...options].sort().join('|')}`;
        const item = getCart().find(i => i.key === key);
        const qtyEl = card.querySelector('[data-card-qty]');
        if (qtyEl) qtyEl.textContent = item ? Number(item.quantity || 0) : 0;
        const limited = card.dataset.trackInventory === '1';
        const stock = Math.max(0, Number(card.dataset.productStock || 0));
        const plus = card.querySelector('[data-card-plus]');
        if (plus) plus.disabled = limited && Number(item?.quantity || 0) >= stock;
        card.classList.toggle('has-cart-item', Boolean(item));
    };
    const refreshAllCards = () => document.querySelectorAll('[data-card]').forEach(refreshCard);
    const cardData = (card) => ({
        id: card.dataset.productId,
        name: card.dataset.productName,
        price: card.dataset.productPrice,
        image: card.dataset.productImage,
        slug: card.dataset.productSlug,
        stock: card.dataset.productStock,
        trackInventory: card.dataset.trackInventory,
    });
    const changeCardQuantity = (card, delta) => {
        if (card.dataset.hasOptions === '1' && cardOptions(card).length === 0) {
            card.querySelector('[data-card-option-group]')?.classList.add('option-required');
            return;
        }
        const options = cardOptions(card);
        const key = `${card.dataset.productId}:${[...options].sort().join('|')}`;
        const cart = getCart();
        const existing = cart.find(i => i.key === key);
        if (delta > 0) {
            const added = addItem(cardData(card), delta, options);
            if (!added) card.classList.add('stock-limit');
        } else if (existing) {
            existing.quantity = Math.max(0, Number(existing.quantity || 0) - 1);
            if (existing.quantity === 0) cart.splice(cart.indexOf(existing), 1);
            saveCart(cart);
            updateCartUI();
        }
        refreshCard(card);
    };
    document.querySelectorAll('[data-card]').forEach(card => {
        card.querySelector('[data-card-plus]')?.addEventListener('click', () => changeCardQuantity(card, 1));
        card.querySelector('[data-card-minus]')?.addEventListener('click', () => changeCardQuantity(card, -1));
        card.querySelectorAll('[data-card-option]').forEach(option => option.addEventListener('click', () => {
            card.querySelectorAll('[data-card-option]').forEach(el => el.classList.remove('selected'));
            option.classList.add('selected');
            card.querySelector('[data-card-option-group]')?.classList.remove('option-required');
            refreshCard(card);
        }));
        refreshCard(card);
    });

    const search = document.querySelector('[data-store-search]');
    if (search) search.addEventListener('input', () => { const q=search.value.toLowerCase().trim(); document.querySelectorAll('.store-product').forEach(card => card.hidden = q !== '' && !card.dataset.name.includes(q)); });

    const qty = document.querySelector('[data-qty]');
    if (qty) {
        const maxQty = () => qty.max ? Math.max(1, Number(qty.max)) : Infinity;
        const clampQty = (value) => Math.min(maxQty(), Math.max(1, Number.isFinite(Number(value)) ? Number(value) : 1));
        document.querySelector('[data-qty-minus]')?.addEventListener('click',()=>qty.value=clampQty(Number(qty.value||1)-1));
        document.querySelector('[data-qty-plus]')?.addEventListener('click',()=>qty.value=clampQty(Number(qty.value||1)+1));
        qty.addEventListener('input',()=>qty.value=clampQty(qty.value));
    }
    const detailAdd = document.querySelector('[data-detail-add]');
    if (detailAdd) detailAdd.addEventListener('click', () => {
        const options = [...document.querySelectorAll('[data-option].selected')].map(x=>x.value);
        if (window.DUKAME_PRODUCT_OPTIONS?.length && options.length === 0) { document.querySelector('[data-option-group]')?.classList.add('option-required'); return; }
        const added = addItem({id:detailAdd.dataset.productId,name:detailAdd.dataset.productName,price:detailAdd.dataset.productPrice,image:detailAdd.dataset.productImage,slug:detailAdd.dataset.productSlug,stock:detailAdd.dataset.productStock,trackInventory:detailAdd.dataset.trackInventory}, Math.max(1,Number(qty?.value||1)), options);
        if (!added) {
            const stockNote=document.querySelector('.product-stock-note');
            if(stockNote) stockNote.textContent='That quantity is not available.';
            return;
        }
        const original=detailAdd.textContent; detailAdd.textContent='Added to cart'; setTimeout(()=>detailAdd.textContent=original,1000);
    });
    document.querySelectorAll('[data-option]').forEach(btn=>btn.addEventListener('click',()=>{ document.querySelectorAll('[data-option]').forEach(x=>x.classList.remove('selected')); btn.classList.add('selected'); document.querySelector('[data-option-group]')?.classList.remove('option-required'); }));
    document.querySelector('[data-read-more]')?.addEventListener('click', e=>{document.querySelector('[data-description]')?.classList.toggle('expanded'); e.currentTarget.textContent=document.querySelector('[data-description]')?.classList.contains('expanded')?'Show less':'Read more';});

    if (window.DUKAME_CART_PAGE) renderCart();
    if (window.DUKAME_CHECKOUT_PAGE) renderCheckout();
    if (window.DUKAME_TRACK_PAGE) bindTracking();
    updateCartUI();
    refreshAllCards();

    function activeStore() { return localStorage.getItem('dukame_active_store') || ''; }
    function pageCart() { const slug=activeStore(); return slug ? JSON.parse(localStorage.getItem(`dukame_cart_${slug}`)||'[]') : []; }
    function pageCurrency() { return localStorage.getItem('dukame_active_currency') || store?.currency || 'KES'; }
    function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}

    function renderCart(){
        const root=document.getElementById('cartRoot'), slug=activeStore(), cart=pageCart();
        if(!slug){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Your cart is empty</h1><p>Open a shop and add something you love.</p><a class="customer-primary-action inline" href="/">Browse Dukame</a></div>';return;}
        if(!cart.length){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Your cart is empty</h1><p>Add products from this store and they will appear here.</p><a class="customer-primary-action inline" href="/'+encodeURIComponent(slug)+'">Continue shopping</a></div>';return;}
        const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
        root.innerHTML=`<div class="customer-section-heading"><div><span class="customer-eyebrow">YOUR ORDER</span><h1>Cart</h1></div><span class="customer-count">${cart.length} products</span></div><div class="cart-layout"><div class="cart-items">${cart.map((i,idx)=>{const limited=i.trackInventory===true||i.trackInventory==='1';const max=limited?Number(i.stock||0):Infinity;return `<div class="cart-item"><div class="cart-item-image">${i.image?`<img src="${escapeHtml(i.image)}" alt="">`:'◇'}</div><div class="cart-item-info"><strong>${escapeHtml(i.name)}</strong>${i.options?.length?`<small>${escapeHtml(i.options.join(' · '))}</small>`:''}<span>${pageCurrency()} ${Number(i.price).toLocaleString('en-KE',{minimumFractionDigits:2})}</span><div class="cart-item-actions"><button data-cart-minus="${idx}">−</button><b>${i.quantity}</b><button data-cart-plus="${idx}" ${limited&&i.quantity>=max?'disabled':''}>+</button><button class="remove-cart" data-cart-remove="${idx}">Remove</button></div>${limited?`<small class="cart-stock-note">${Math.max(0,max-i.quantity)} remaining in stock</small>`:''}</div><strong>${pageCurrency()} ${(i.price*i.quantity).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div>`;}).join('')}</div><aside class="cart-summary"><span>Subtotal</span><strong>${pageCurrency()} ${total.toLocaleString('en-KE',{minimumFractionDigits:2})}</strong><small>Final delivery details are collected at checkout.</small><a class="customer-primary-action inline" href="/checkout">Continue to checkout</a></aside></div>`;
        root.querySelectorAll('[data-cart-minus]').forEach(b=>b.onclick=()=>changeCart(Number(b.dataset.cartMinus),-1)); root.querySelectorAll('[data-cart-plus]').forEach(b=>b.onclick=()=>changeCart(Number(b.dataset.cartPlus),1)); root.querySelectorAll('[data-cart-remove]').forEach(b=>b.onclick=()=>{const c=pageCart();c.splice(Number(b.dataset.cartRemove),1);savePageCart(c);renderCart();});
    }
    function savePageCart(c){const slug=activeStore();localStorage.setItem(`dukame_cart_${slug}`,JSON.stringify(c));}
    function changeCart(i,d){const c=pageCart();if(!c[i])return;const limited=c[i].trackInventory===true||c[i].trackInventory==='1';const max=limited?Number(c[i].stock||0):Infinity;c[i].quantity=Math.min(max,Math.max(0,Number(c[i].quantity||0)+d));if(c[i].quantity===0)c.splice(i,1);savePageCart(c);renderCart();}

    async function renderCheckout(){
        const root=document.getElementById('checkoutRoot'), slug=activeStore(), cart=pageCart();
        if(!slug||!cart.length){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Nothing to checkout</h1><p>Add products before checking out.</p><a class="customer-primary-action inline" href="'+(slug?'/'+encodeURIComponent(slug):'/')+'">Back to shop</a></div>';return;}
        try{
            const r=await fetch('/api/v1/stores/'+encodeURIComponent(slug),{headers:{Accept:'application/json'}}); const json=await r.json();
            if(!r.ok||!json.success) throw new Error('This store is not available right now.');
            const checkoutStore=json.data, methods=checkoutStore.payment_methods||{}, fulfillment=checkoutStore.fulfillment||{};
            const subtotal=cart.reduce((s,i)=>s+i.price*i.quantity,0), currency=pageCurrency();
            const hasDelivery=!!fulfillment.delivery, hasPickup=!!fulfillment.pickup;
            const defaultFulfillment=hasDelivery?'delivery':'pickup';
            const feeFor=(method,zoneId)=>{
                if(method==='pickup') return 0;
                let fee=Number(fulfillment.flat_fee||0);
                if(fulfillment.pricing_mode==='zone'){
                    const zone=(fulfillment.zones||[]).find(z=>Number(z.id)===Number(zoneId)); fee=zone?Number(zone.fee):0;
                }
                const minimum=fulfillment.free_delivery_minimum===null?null:Number(fulfillment.free_delivery_minimum);
                return minimum&&subtotal>=minimum?0:fee;
            };
            const fulfillmentOptions=`${hasDelivery?`<label class="fulfillment-option"><input type="radio" name="fulfillment_method" value="delivery" ${defaultFulfillment==='delivery'?'checked':''}><span><strong>Delivery</strong><small>${fulfillment.pricing_mode==='zone'?'Choose your delivery area to see the charge.':'Delivery fee is calculated from the store settings.'}</small></span></label>`:''}${hasPickup?`<label class="fulfillment-option"><input type="radio" name="fulfillment_method" value="pickup" ${defaultFulfillment==='pickup'?'checked':''}><span><strong>Store pickup</strong><small>Collect your order from ${escapeHtml(fulfillment.pickup_address||'the store')}.</small></span></label>`:''}`;
            if(!hasDelivery&&!hasPickup) throw new Error('This store has not enabled delivery or store pickup yet. Please contact the merchant.');
            const zones=(fulfillment.zones||[]).map(z=>`<option value="${escapeHtml(z.id)}">${escapeHtml(z.name)} — ${currency} ${Number(z.fee).toLocaleString('en-KE',{minimumFractionDigits:2})}</option>`).join('');
            root.innerHTML=`<div class="customer-section-heading"><div><span class="customer-eyebrow">ALMOST THERE</span><h1>Checkout</h1><p class="checkout-intro">Choose delivery or pickup, then enter your details. No account is required.</p></div></div>
            <div class="checkout-layout"><form id="checkoutForm" class="checkout-form" novalidate><section>
              <h2>How would you like to receive your order?</h2><div class="checkout-fieldset">${fulfillmentOptions}</div>
              <div id="deliveryFields" class="checkout-fieldset"><label>Delivery location / address <span>(required for delivery)</span><textarea name="delivery_address" rows="3" maxlength="500" placeholder="Kasarani, Nairobi"></textarea></label>${fulfillment.pricing_mode==='zone'&&hasDelivery?`<label id="zoneField">Delivery area<select name="delivery_zone_id"><option value="">Choose your area</option>${zones}</select></label>`:''}</div>
              <div id="pickupInfo" class="payment-unavailable" hidden><strong>Store pickup</strong><p>${escapeHtml(fulfillment.pickup_address||'Pickup is available from the merchant store.')}</p>${fulfillment.pickup_instructions?`<small>${escapeHtml(fulfillment.pickup_instructions)}</small>`:''}</div>
              <h2 class="mt-2">Your details</h2>
              <div class="checkout-name-grid"><label>First name<input name="first_name" autocomplete="given-name" maxlength="100" required placeholder="John"></label><label>Last name<input name="last_name" autocomplete="family-name" maxlength="100" required placeholder="Kamau"></label></div>
              <label>Phone number<input name="customer_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" required placeholder="0712 345 678"><small>We'll use this to identify your order when you track it.</small></label>
              <label>Email <span>(optional)</span><input name="customer_email" type="email" autocomplete="email" maxlength="190" placeholder="you@example.com"></label>
              <label>Notes <span>(optional)</span><textarea name="notes" rows="2" maxlength="1000" placeholder="Any instructions for the merchant?"></textarea>
              <div class="payment-choice"><div><h3>Payment</h3><p class="small text-secondary mb-3">Choose an available payment method for this fulfilment option.</p></div><div id="paymentOptions"></div><div id="noPayment" class="payment-unavailable" hidden><strong>No payment method available</strong><p>The merchant has not enabled a payment method for this option. Please contact the store.</p></div></div>
            </section><div class="checkout-actions"><button class="customer-primary-action" type="submit" data-order-action="web">Place order</button><button class="customer-whatsapp-action" type="button" data-order-action="whatsapp">Order on WhatsApp</button></div><p class="checkout-note">Your order is saved in Dukame first. WhatsApp is optional and never required to create the order.</p><div class="form-error" data-checkout-error hidden></div></form>
            <aside class="checkout-summary"><h2>Your order</h2>${cart.map(i=>`<div class="checkout-line"><span>${escapeHtml(i.name)} × ${i.quantity}${i.options?.length?`<small>${escapeHtml(i.options.join(' · '))}</small>`:''}</span><strong>${currency} ${(i.price*i.quantity).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div>`).join('')}<div class="checkout-line"><span>Subtotal</span><strong id="checkoutSubtotal">${currency} ${subtotal.toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div><div class="checkout-line"><span>Fulfilment <small id="fulfillmentSummary">${defaultFulfillment==='pickup'?'Store pickup':'Delivery'}</small></span><strong id="deliveryFee">${defaultFulfillment==='pickup'?'Free':currency+' '+feeFor('delivery',null).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div><div class="checkout-total"><span>Total</span><strong id="checkoutTotal">${currency} ${(subtotal+feeFor(defaultFulfillment,null)).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div><div id="freeDeliveryNote" class="checkout-fee-note"></div></aside></div>`;
            const form=document.getElementById('checkoutForm'), deliveryFields=document.getElementById('deliveryFields'), pickupInfo=document.getElementById('pickupInfo'), paymentOptions=document.getElementById('paymentOptions'), noPayment=document.getElementById('noPayment'), submit=form.querySelector('[data-order-action=web]');
            const sync=()=>{
                const method=form.querySelector('input[name=fulfillment_method]:checked')?.value||defaultFulfillment;
                const zone=form.querySelector('select[name=delivery_zone_id]')?.value||'';
                const fee=feeFor(method,zone); const isPickup=method==='pickup';
                deliveryFields.hidden=isPickup; pickupInfo.hidden=!isPickup;
                const address=form.querySelector('[name=delivery_address]'); if(address) address.required=!isPickup;
                const zoneSelect=form.querySelector('select[name=delivery_zone_id]'); if(zoneSelect) zoneSelect.required=!isPickup;
                let options='';
                if(methods.mpesa) options+='<label class="payment-option"><input type="radio" name="payment_method" value="mpesa"><span><strong>M-Pesa</strong><small>Pay securely with an M-Pesa STK Push.</small></span></label>';
                if(method==='delivery'&&methods.cash_on_delivery) options+='<label class="payment-option"><input type="radio" name="payment_method" value="cash_on_delivery"><span><strong>Cash on delivery</strong><small>Pay when your order is delivered.</small></span></label>';
                if(method==='pickup'&&methods.cash_on_pickup) options+='<label class="payment-option"><input type="radio" name="payment_method" value="cash_on_pickup"><span><strong>Pay at pickup</strong><small>Pay in cash when you collect your order.</small></span></label>';
                paymentOptions.innerHTML=options;
                const first=paymentOptions.querySelector('input'); if(first) first.checked=true;
                noPayment.hidden=!!options;
                submit.disabled=!options;
                document.getElementById('fulfillmentSummary').textContent=isPickup?'Store pickup':(fulfillment.pricing_mode==='zone'?(zone?((fulfillment.zones||[]).find(z=>Number(z.id)===Number(zone))?.name||'Delivery'):'Choose delivery area'):'Delivery');
                document.getElementById('deliveryFee').textContent=isPickup?'Free':(fee===0?'Free':currency+' '+fee.toLocaleString('en-KE',{minimumFractionDigits:2}));
                document.getElementById('checkoutTotal').textContent=currency+' '+(subtotal+fee).toLocaleString('en-KE',{minimumFractionDigits:2});
                const minimum=fulfillment.free_delivery_minimum===null?null:Number(fulfillment.free_delivery_minimum); document.getElementById('freeDeliveryNote').textContent=(!isPickup&&minimum&&subtotal>=minimum)?'Free delivery applied to this order.':(!isPickup&&minimum?'Free delivery applies from '+currency+' '+minimum.toLocaleString('en-KE',{minimumFractionDigits:2})+'.':'');
            };
            form.addEventListener('change',e=>{if(e.target.name==='fulfillment_method'||e.target.name==='delivery_zone_id')sync();}); sync();
            const submitOrder=async(channel)=>{
                if(!form.reportValidity()) return;
                const selectedPayment=form.querySelector('input[name=payment_method]:checked'); if(!selectedPayment){const err=form.querySelector('[data-checkout-error]');err.textContent='Please select a payment method.';err.hidden=false;return;}
                const clicked=channel==='whatsapp'?form.querySelector('[data-order-action=whatsapp]'):submit, buttons=form.querySelectorAll('button[type=submit],[data-order-action=whatsapp]'), err=form.querySelector('[data-checkout-error]'); err.hidden=true; buttons.forEach(b=>b.disabled=true); clicked.textContent=channel==='whatsapp'?'Creating order…':'Placing order…';
                const data=Object.fromEntries(new FormData(form).entries()); data.shop_slug=slug; data.order_channel=channel; data.items=cart.map(i=>({product_id:i.id,quantity:i.quantity,options:i.options||[]}));
                try{const r=await fetch('/api/v1/orders',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data)});const json=await r.json();if(!r.ok||!json.success)throw new Error(json.error?.message||'Could not create your order.');const order=json.data.order;localStorage.removeItem(`dukame_cart_${slug}`);const whatsapp=json.data.whatsapp_url;const mpesa=json.data.mpesa;const fulfillmentText=order.fulfillment_method==='pickup'?'Store pickup':'Delivery';const paymentNote=mpesa?.status==='pending'?'<div class="payment-pending-note"><strong>Check your phone.</strong> An M-Pesa STK Push has been sent. Complete the payment to finish your payment.</div>':(order.payment_status==='paid'?'<div class="payment-success-note"><strong>Payment received.</strong> Your payment has been confirmed.</div>':'');root.innerHTML=`<div class="order-success"><div class="success-icon">✓</div><span class="customer-eyebrow">ORDER PLACED</span><h1>Thank you, ${escapeHtml(order.customer_first_name||'')}.</h1><p>Your order <strong>${escapeHtml(order.order_number)}</strong> has been placed with ${escapeHtml(order.shop_name||'')}.</p><div class="success-order-number">${escapeHtml(order.order_number)}</div><p class="success-note">${fulfillmentText==='Store pickup'?'You chose store pickup.':'Your delivery details have been saved.'} Keep your order number and phone number to track your order.</p>${paymentNote}${channel==='whatsapp'&&whatsapp?`<a class="customer-whatsapp-action inline" href="${escapeHtml(whatsapp)}">Open WhatsApp and Send Order</a>`:''}${channel!=='whatsapp'&&whatsapp?`<a class="secondary-action" href="${escapeHtml(whatsapp)}">Also send order on WhatsApp</a>`:''}<a class="customer-primary-action inline" href="/track">Track your order</a><a class="secondary-action" href="/${encodeURIComponent(slug)}">Continue shopping</a></div>`;if(channel==='whatsapp'&&whatsapp)window.location.href=whatsapp;}catch(ex){err.textContent=ex.message;err.hidden=false;buttons.forEach(b=>b.disabled=false);clicked.textContent=channel==='whatsapp'?'Order on WhatsApp':'Place order';}}
            form.addEventListener('submit',e=>{e.preventDefault();submitOrder('web');});form.querySelector('[data-order-action=whatsapp]').addEventListener('click',()=>submitOrder('whatsapp'));
        }catch(ex){root.innerHTML='<div class="customer-empty"><div>!</div><h1>Checkout unavailable</h1><p>'+escapeHtml(ex.message)+'</p><a class="customer-primary-action inline" href="/'+encodeURIComponent(slug)+'">Back to shop</a></div>';}
    }

    function bindTracking(){const form=document.getElementById('trackForm');form?.addEventListener('submit',async e=>{e.preventDefault();const err=document.querySelector('[data-track-error]'),result=document.getElementById('trackResult');err.hidden=true;result.innerHTML='';const data=Object.fromEntries(new FormData(form).entries());try{const r=await fetch('/api/v1/orders/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await r.json();if(!r.ok||!json.success)throw new Error(json.error?.message||'Order not found.');const o=json.data;result.innerHTML=`<div class="tracking-result"><div class="tracking-top"><div><small>${escapeHtml(o.shop_name)}</small><h2>${escapeHtml(o.order_number)}</h2></div><span class="status-badge status-${escapeHtml(o.status)}">${escapeHtml(o.status.replace('_',' '))}</span></div><div class="tracking-steps"><span class="${['pending','confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Order received</span><span class="${['confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Confirmed</span><span class="${['preparing','ready','delivered'].includes(o.status)?'done':''}">Preparing</span><span class="${['ready','delivered'].includes(o.status)?'done':''}">Ready</span><span class="${o.status==='delivered'?'done':''}">Delivered</span></div><div class="tracking-total"><span>${o.fulfillment_method==='pickup'?'Pickup':'Delivery'}</span><strong>${o.fulfillment_method==='pickup'?'Store pickup':escapeHtml(o.delivery_zone_name||'Delivery')}</strong></div><div class="tracking-total"><span>Total</span><strong>${escapeHtml(o.currency)} ${Number(o.total).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div></div>`;}catch(ex){err.textContent=ex.message;err.hidden=false;}})}
});

// Copy buttons on merchant pages.
document.addEventListener('click', async (event) => { const button=event.target.closest('[data-copy-text]'); if(!button)return; const text=button.getAttribute('data-copy-text')||'';try{await navigator.clipboard.writeText(new URL(text,window.location.origin).href);const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400);}catch(_){}});
