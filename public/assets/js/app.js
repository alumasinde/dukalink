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
        if(!slug||!cart.length){root.innerHTML='<div class="customer-empty"><div>🛒</div><h1>Nothing to checkout</h1><p>Add products before checking out.</p><a class="customer-primary-action inline" href="/'+encodeURIComponent(slug||'')+'">Back to shop</a></div>';return;}
        const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
        root.innerHTML=`<div class="customer-section-heading"><div><span class="customer-eyebrow">ALMOST THERE</span><h1>Checkout</h1></div></div><div class="checkout-layout"><form id="checkoutForm" class="checkout-form"><section><h2>Your details</h2><label>Full name<input name="customer_name" autocomplete="name" required placeholder="John Kamau"></label><label>Phone number<input name="customer_phone" type="tel" autocomplete="tel" required placeholder="0712 345 678"></label><label>Email <span>(optional)</span><input name="customer_email" type="email" autocomplete="email" placeholder="you@example.com"></label><label>Delivery location / address <span>(optional)</span><textarea name="delivery_address" rows="3" placeholder="Kasarani, Nairobi"></textarea><label>Notes <span>(optional)</span><textarea name="notes" rows="2" placeholder="Any delivery instructions?"></textarea></label></section><button class="customer-primary-action" type="submit">Order on WhatsApp</button><p class="checkout-note">Your order is created in Dukame first. WhatsApp then opens with a ready-to-send order message for the store.</p><div class="form-error" data-checkout-error hidden></div></form><aside class="checkout-summary"><h2>Your order</h2>${cart.map(i=>`<div class="checkout-line"><span>${escapeHtml(i.name)} × ${i.quantity}${i.options?.length?`<small>${escapeHtml(i.options.join(' · '))}</small>`:''}</span><strong>${pageCurrency()} ${(i.price*i.quantity).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div>`).join('')}<div class="checkout-total"><span>Total</span><strong>${pageCurrency()} ${total.toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div></aside></div>`;
        document.getElementById('checkoutForm').addEventListener('submit', async e=>{e.preventDefault();const btn=e.currentTarget.querySelector('button[type=submit]'),err=document.querySelector('[data-checkout-error]');err.hidden=true;btn.disabled=true;btn.textContent='Creating order…';const data=Object.fromEntries(new FormData(e.currentTarget).entries());data.shop_slug=slug;data.payment_method='whatsapp';data.items=cart.map(i=>({product_id:i.id,quantity:i.quantity,options:i.options||[]}));try{const r=await fetch('/api/v1/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await r.json();if(!r.ok||!json.success)throw new Error(json.error?.message||'Could not create your order.');const order=json.data.order;localStorage.removeItem(`dukame_cart_${slug}`);root.innerHTML=`<div class="order-success"><div class="success-icon">✓</div><span class="customer-eyebrow">ORDER CREATED</span><h1>You're all set.</h1><p>Your order <strong>${escapeHtml(order.order_number)}</strong> has been created. Send it to the store on WhatsApp so they can confirm it.</p><div class="success-order-number">${escapeHtml(order.order_number)}</div>${json.data.whatsapp_url?`<a class="customer-primary-action inline" href="${escapeHtml(json.data.whatsapp_url)}">Open WhatsApp & Send Order</a>`:'<div class="form-error">This store has not configured a WhatsApp number yet.</div>'}<a class="secondary-action" href="/track">Track your order</a></div>`; }catch(ex){err.textContent=ex.message;err.hidden=false;btn.disabled=false;btn.textContent='Order on WhatsApp';}});
    }

    function bindTracking(){const form=document.getElementById('trackForm');form?.addEventListener('submit',async e=>{e.preventDefault();const err=document.querySelector('[data-track-error]'),result=document.getElementById('trackResult');err.hidden=true;result.innerHTML='';const data=Object.fromEntries(new FormData(form).entries());try{const r=await fetch('/api/v1/orders/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await r.json();if(!r.ok||!json.success)throw new Error(json.error?.message||'Order not found.');const o=json.data;result.innerHTML=`<div class="tracking-result"><div class="tracking-top"><div><small>${escapeHtml(o.shop_name)}</small><h2>${escapeHtml(o.order_number)}</h2></div><span class="status-badge status-${escapeHtml(o.status)}">${escapeHtml(o.status.replace('_',' '))}</span></div><div class="tracking-steps"><span class="${['pending','confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Order received</span><span class="${['confirmed','preparing','ready','delivered'].includes(o.status)?'done':''}">Confirmed</span><span class="${['preparing','ready','delivered'].includes(o.status)?'done':''}">Preparing</span><span class="${['ready','delivered'].includes(o.status)?'done':''}">Ready</span><span class="${o.status==='delivered'?'done':''}">Delivered</span></div><div class="tracking-total"><span>Total</span><strong>${escapeHtml(o.currency)} ${Number(o.total).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></div></div>`;}catch(ex){err.textContent=ex.message;err.hidden=false;}})}
});

// Copy buttons on merchant pages.
document.addEventListener('click', async (event) => { const button=event.target.closest('[data-copy-text]'); if(!button)return; const text=button.getAttribute('data-copy-text')||'';try{await navigator.clipboard.writeText(new URL(text,window.location.origin).href);const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400);}catch(_){}});
