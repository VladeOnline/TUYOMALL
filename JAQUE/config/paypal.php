<?php
declare(strict_types=1);

/*
 * PayPal webhook configuration.
 *
 * 1. Create a REST app in PayPal Developer.
 * 2. Add this webhook URL:
 *    https://tudominio.com/billing/paypal-webhook.php
 * 3. Copy the webhook ID here.
 *
 * Use sandbox values only while testing.
 */
const PAYPAL_CLIENT_ID = 'TU_PAYPAL_CLIENT_ID';
const PAYPAL_CLIENT_SECRET = 'TU_PAYPAL_CLIENT_SECRET';
const PAYPAL_WEBHOOK_ID = 'TU_PAYPAL_WEBHOOK_ID';
const PAYPAL_PLAN_ID = 'TU_PAYPAL_PLAN_ID';
const PAYPAL_RETURN_URL = 'https://www.tuyomall.com/gracias-premium.html';
const PAYPAL_CANCEL_URL = 'https://www.tuyomall.com/premium-checkout.html?cancelado=1';
const PAYPAL_TAX_PERCENTAGE = '13';
const PAYPAL_API_BASE = 'https://api-m.paypal.com';
