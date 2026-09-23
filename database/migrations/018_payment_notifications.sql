INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'payment_received', 'Payment received for order {Order Number}: {Currency} {Order Total}. Thank you for shopping with {Store Name}.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='payment_received'
WHERE nt.id IS NULL;

INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'payment_failed', 'We could not confirm payment for order {Order Number}. Please contact {Store Name} if you need help.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='payment_failed'
WHERE nt.id IS NULL;
