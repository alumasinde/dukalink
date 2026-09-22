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
        if (!cartKey) return;
        const cart = getCart();
        const normalizedOptions = [...options].sort();
        const key = `${data.id}:${normalizedOptions.join('|')}`;
        const existing = cart.find(item => item.key === key);
        if (existing) existing.quantity += quantity;
        else cart.push({key, id: Number(data.id), name: data.name, price: Number(data.price), image: data.image || '', slug: data.slug || '', quantity, options: normalizedOptions});
        saveCart(cart); updateCartUI();
    };

    document.querySelectorAll('[data-add-cart]').forEach(button => button.addEventListener('click', () => {
        addItem({id:button.dataset.productId,name:button.dataset.productName,price:button.dataset.productPrice,image:button.dataset.productImage,slug:button.dataset.productSlug});
        flashAdded(button);
    }));

    const search = document.querySelector('[data-store-search]');
    if (search) search.addEventListener('input', () => { const q=search.value.toLowerCase().trim(); document.querySelectorAll('.store-product').forEach(card => card.hidden = q !== '' && !card.dataset.name.includes(q)); });

    const qty = document.querySelector('[data-qty]');
    if (qty) {
        document.querySelector('[data-qty-minus]')?.addEventListener('click',()=>qty.value=Math.max(1,Number(qty.value||1)-1));
        document.querySelector('[data-qty-plus]')?.addEventListener('click',()=>qty.value=Math.max(1,Number(qty.value||1)+1));
    }
    const detailAdd = document.querySelector('[data-detail-add]');
    if (detailAdd) detailAdd.addEventListener('click', () => {
        const options = [...document.querySelectorAll('[data-option].selected')].map(x=>x.value);
        if (window.DUKAME_PRODUCT_OPTIONS?.length && options.length === 0) { document.querySelector('[data-option-group]')?.classList.add('option-required'); return; }
        addItem({id:detailAdd.dataset.productId,name:detailAdd.dataset.productName,price:detailAdd.dataset.productPrice,image:detailAdd.dataset.productImage,slug:detailAdd.dataset.productSlug}, Math.max(1,Number(qty?.value||1)), options);
        const original=detailAdd.textContent; detailAdd.textContent='Added to cart ✓'; setTimeout(()=>detailAdd.textContent=original,1000);
    });
    document.querySelectorAll('[data-option]').forEach(btn=>btn.addEventListener('click',()=>{ document.querySelectorAll('[data-option]').forEach(x=>x.classList.remove('selected')); btn.classList.add('selected'); document.querySelector('[data-option-group]')?.classList.remove('option-required'); }));
    document.querySelector('[data-read-more]')?.addEventListener('click', e=>{document.querySelector('[data-description]')?.classList.toggle('expanded'); e.currentTarget.textContent=document.querySelector('[data-description]')?.classList.contains('expanded')?'Show less':'Read more';});

    if (window.DUKAME_CART_PAGE) renderCart();
    if (window.DUKAME_CHECKOUT_PAGE) renderCheckout();
    if (window.DUKAME_TRACK_PAGE) bindTracking();
    updateCartUI();

    function activeStore() { return localStorage.getItem('dukame_active_store') || ''; }
    function pageCart() { const slug=activeStore(); return slug ? JSON.parse(localStorage.getItem(`dukame_cart_${slug}`)||'[]') : []; }
    function pageCurrency() { return localStorage.getItem('dukame_active_currency') || store?.currency || 'KES'; }
    function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}

    function renderCart(){
        const root=document.getElementById('cartRoot'), slug=activeStore(), cart=pageCart();
        if(!slug){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Your cart is empty</h1><p>Open a shop and add something you love.</p><a class="customer-primary-action inline" href="/">Browse Dukame</a></div>';return;}
        if(!cart.length){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Your cart is empty</h1><p>Add products from this store and they will appear here.</p><a class="customer-primary-action inline" href="/'+encodeURIComponent(slug)+'">Continue shopping</a></div>';return;}
        const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
        root.innerHTML=`<div class="customer-section-heading"><div><span class="customer-eyebrow">YOUR ORDER</span><h1>Cart</h1></div><span class="customer-count">${cart.length} products</span></div><div class="cart-layout"><div class="cart-items">${cart.map((i,idx)=>`<div class="cart-item"><div class="cart-item-image">${i.image?`<img src="${escapeHtml(i.image)}" alt="">`:'◇'}</div><div class="cart-item-info"><strong>${escapeHtml(i.name)}</strong>${i.options?.length?`<small>${escapeHtml(i.options.join(' · '))}</small>`:''}<span>${pageCurrency()} ${Number(i.price).toLocaleString('en-KE',{minimumFractionDigits:2})}</span><div class="cart-item-actions"><button data-cart-minus="${idx}">−</button><b>${i.quantity}</b><button data-cart-plus="${idx}">+</button><button class="remove-cart" data-cart-remove="${idx}">Remove</button></div></div><strong>${pageCurrency()} ${(i.price*i.quantity).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div>`).join('')}</div><aside class="cart-summary"><span>Subtotal</span><strong>${pageCurrency()} ${total.toLocaleString('en-KE',{minimumFractionDigits:2})}</strong><small>Final delivery details are collected at checkout.</small><a class="customer-primary-action inline" href="/checkout">Continue to checkout</a></aside></div>`;
        root.querySelectorAll('[data-cart-minus]').forEach(b=>b.onclick=()=>changeCart(Number(b.dataset.cartMinus),-1)); root.querySelectorAll('[data-cart-plus]').forEach(b=>b.onclick=()=>changeCart(Number(b.dataset.cartPlus),1)); root.querySelectorAll('[data-cart-remove]').forEach(b=>b.onclick=()=>{const c=pageCart();c.splice(Number(b.dataset.cartRemove),1);savePageCart(c);renderCart();});
    }
    function savePageCart(c){const slug=activeStore();localStorage.setItem(`dukame_cart_${slug}`,JSON.stringify(c));}
    function changeCart(i,d){const c=pageCart();c[i].quantity=Math.max(0,c[i].quantity+d);if(c[i].quantity===0)c.splice(i,1);savePageCart(c);renderCart();}

    function renderCheckout(){
        const root=document.getElementById('checkoutRoot'), slug=activeStore(), cart=pageCart();
        if(!slug||!cart.length){
            root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Nothing to checkout</h1><p>Add products before checking out.</p><a class="customer-primary-action inline" href="'+(slug?'/'+encodeURIComponent(slug):'/')+'">Back to shop</a></div>';
            return;
        }

        const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
        root.innerHTML=`<div class="customer-section-heading"><div><span class="customer-eyebrow">ALMOST THERE</span><h1>Checkout</h1><p class="checkout-intro">Enter your details and place your order. No account is required.</p></div></div>
        <div class="checkout-layout">
          <form id="checkoutForm" class="checkout-form" novalidate>
            <section>
              <h2>Your details</h2>
              <div class="checkout-name-grid">
                <label>First name<input name="first_name" autocomplete="given-name" maxlength="100" required placeholder="John"></label>
                <label>Last name<input name="last_name" autocomplete="family-name" maxlength="100" required placeholder="Kamau"></label>
              </div>
              <label>Phone number<input name="customer_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" required placeholder="0712 345 678"><small>We'll use this to identify your order when you track it.</small></label>
              <label>Email <span>(optional)</span><input name="customer_email" type="email" autocomplete="email" maxlength="190" placeholder="you@example.com"></label>
              <label>Delivery location / address <span>(optional)</span><textarea name="delivery_address" rows="3" maxlength="500" placeholder="Kasarani, Nairobi"></textarea>
              <label>Notes <span>(optional)</span><textarea name="notes" rows="2" maxlength="1000" placeholder="Any delivery instructions?"></textarea></label>
              <div class="payment-choice"><h3>Payment</h3>${store?.payment_methods?.mpesa?`<label class="payment-option"><input type="radio" name="payment_method" value="mpesa" ${!store?.payment_methods?.cash_on_delivery?'checked':''}><span><strong>M-Pesa</strong><small>Pay securely with an M-Pesa STK Push.</small></span></label>`:''}${store?.payment_methods?.cash_on_delivery?`<label class="payment-option"><input type="radio" name="payment_method" value="cash_on_delivery" ${store?.payment_methods?.mpesa?'':'checked'}><span><strong>Cash on delivery</strong><small>Pay the merchant when your order is delivered.</small></span></label>`:''}${!store?.payment_methods?.mpesa&&!store?.payment_methods?.cash_on_delivery?`<div class="form-error">Payment method not available
This store hasn't enabled online payments yet. <br>Please contact the merchant to arrange payment.</div>`:''}</div>
            </section>
            <div class="checkout-actions">
              <button class="customer-primary-action" type="submit" data-order-action="web">Place order</button>
              <button class="customer-whatsapp-action" type="button" data-order-action="whatsapp">Order on WhatsApp <span>↗</span></button>
            </div>
            <p class="checkout-note">Your order is saved in Dukame first. WhatsApp is an optional way to send the same order to the store.</p>
            <div class="form-error" data-checkout-error hidden></div>
          </form>
          <aside class="checkout-summary"><h2>Your order</h2>${cart.map(i=>`<div class="checkout-line"><span>${escapeHtml(i.name)} × ${i.quantity}${i.options?.length?`<small>${escapeHtml(i.options.join(' · '))}</small>`:''}</span><strong>${pageCurrency()} ${(i.price*i.quantity).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div>`).join('')}<div class="checkout-total"><span>Total</span><strong>${pageCurrency()} ${total.toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div></aside>
        </div>`;

        const form=document.getElementById('checkoutForm');
        const submitOrder=async(channel)=>{
            if(!form.reportValidity()) return;
            if(!form.querySelector('input[name=payment_method]:checked')) { const err=form.querySelector('[data-checkout-error]'); err.textContent='Please select a payment method.'; err.hidden=false; return; }
            const buttons=form.querySelectorAll('button[type=submit], [data-order-action=whatsapp]');
            const clicked=channel==='whatsapp' ? form.querySelector('[data-order-action=whatsapp]') : form.querySelector('[data-order-action=web]');
            const err=form.querySelector('[data-checkout-error]');
            err.hidden=true;
            buttons.forEach(b=>b.disabled=true);
            clicked.textContent=channel==='whatsapp'?'Creating order…':'Placing order…';
            const data=Object.fromEntries(new FormData(form).entries());
            data.shop_slug=slug;
            data.order_channel=channel;
            data.items=cart.map(i=>({product_id:i.id,quantity:i.quantity,options:i.options||[]}));
            try{
                const r=await fetch('/api/v1/orders',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data)});
                const json=await r.json();
                if(!r.ok||!json.success) throw new Error(json.error?.message||'Could not create your order.');
                const order=json.data.order;
                localStorage.removeItem(`dukame_cart_${slug}`);
                const whatsapp=json.data.whatsapp_url;
                root.innerHTML=`<div class="order-success"><div class="success-icon">✓</div><span class="customer-eyebrow">ORDER PLACED</span><h1>Thank you, ${escapeHtml(order.customer_first_name || '')}.</h1><p>Your order <strong>${escapeHtml(order.order_number)}</strong> has been placed with ${escapeHtml(order.shop_name || '')}.</p><div class="success-order-number">${escapeHtml(order.order_number)}</div><p class="success-note">Keep your order number and phone number so you can track your order anytime.</p>${json.data.mpesa?.status==='pending'?'<div class="payment-pending-note"><strong>Check your phone.</strong> An M-Pesa STK Push has been sent. Complete the payment to finish your payment.</div>':''}${channel==='whatsapp'&&whatsapp?`<a class="customer-whatsapp-action inline" href="${escapeHtml(whatsapp)}">Open WhatsApp & Send Order ↗</a>`:''}${channel!=='whatsapp'&&whatsapp?`<a class="secondary-action" href="${escapeHtml(whatsapp)}">Also send order on WhatsApp ↗</a>`:''}<a class="customer-primary-action inline" href="/track">Track your order</a><a class="secondary-action" href="/${encodeURIComponent(slug)}">Continue shopping</a></div>`;
                if(channel==='whatsapp'&&whatsapp) window.location.href=whatsapp;
            }catch(ex){
                err.textContent=ex.message;err.hidden=false;buttons.forEach(b=>b.disabled=false);
                clicked.textContent=channel==='whatsapp'?'Order on WhatsApp':'Place order';
            }
        };
        form.addEventListener('submit',e=>{e.preventDefault();submitOrder('web');});
        form.querySelector('[data-order-action=whatsapp]').addEventListener('click',()=>submitOrder('whatsapp'));
    }

    function bindTracking(){const form=document.getElementById('trackForm');form?.addEventListener('submit',async e=>{e.preventDefault();const err=document.querySelector('[data-track-error]'),result=document.getElementById('trackResult');err.hidden=true;result.innerHTML='';const data=Object.fromEntries(new FormData(form).entries());try{const r=await fetch('/api/v1/orders/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await r.json();if(!r.ok||!json.success)throw new Error(json.error?.message||'Order not found.');const o=json.data;result.innerHTML=`<div class="tracking-result"><div class="tracking-top"><div><small>${escapeHtml(o.shop_name)}</small><h2>${escapeHtml(o.order_number)}</h2></div><span class="status-badge status-${escapeHtml(o.status)}">${escapeHtml(o.status.replace('_',' '))}</span></div><div class="tracking-steps"><span class="${['pending','confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Order received</span><span class="${['confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Confirmed</span><span class="${['preparing','ready','delivered'].includes(o.status)?'done':''}">Preparing</span><span class="${['ready','delivered'].includes(o.status)?'done':''}">Ready</span><span class="${o.status==='delivered'?'done':''}">Delivered</span></div><div class="tracking-total"><span>Total</span><strong>${escapeHtml(o.currency)} ${Number(o.total).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div></div>`;}catch(ex){err.textContent=ex.message;err.hidden=false;}})}
});

// Copy buttons on merchant pages.
document.addEventListener('click', async (event) => { const button=event.target.closest('[data-copy-text]'); if(!button)return; const text=button.getAttribute('data-copy-text')||'';try{await navigator.clipboard.writeText(new URL(text,window.location.origin).href);const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400);}catch(_){}});
