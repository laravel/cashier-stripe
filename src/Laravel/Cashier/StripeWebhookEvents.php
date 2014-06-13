<?php

namespace Laravel\Cashier;


class StripeWebhookEvents
{
    const CHARGE_SUCCEEDED = 'charge.succeeded';

    const  CHARGE_FAILED = 'charge.failed';

    const CHARGE_REFUNDED = 'charge.refunded';

    const CHARGE_CAPTURED = 'charge.captured';

    const CHARGE_UPDATED = 'charge.updated';

    const CHARGE_DISPUTE_CREATED = 'charge.dispute.create';

    const  CHARGE_DISPUTE_UPDATED ='charge.dispute.updated';

    const CHARGE_DISPUTE_CLOSED = 'charge.dispute.closed';

    const CUSTOMER_CREATED = 'customer.created';

    const CUSTOMER_UPDATED = 'customer.updated';

    const CUSTOMER_DELETED = 'customer.deleted';

    const CUSTOMER_CARD_CREATED = 'customer.card.created';

    const CUSTOMER_CARD_UPDATED = 'customer.card.updated';

    const CUSTOMER_CARD_DELETED = 'customer.card.deleted';

    const CUSTOMER_SUBSCRIPTION_CREATED = 'customer.subscription.created';

    const CUSTOMER_SUBSCRIPTION_UPDATED = 'customer.subscription.updated';

    const CUSTOMER_SUBSCRIPTION_DELETED = 'customer.subscription.deleted';

    const CUSTOMER_SUBSCRIPTION_TRIAL_WILL_END = 'customer.subscription.trial_will_end';

    const CUSTOMER_DISCOUNT_CREATED = 'customer.discount.created';

    const CUSTOMER_DISCOUNT_UPDATED = 'customer.discount.updated';

    const CUSTOMER_DISCOUNT_DELETED = 'customer.discount.deleted';

    const INVOICE_CREATED = 'invoice.created';

    const INVOICE_UPDATED = 'invoice.updated';

    const INVOICE_PAYMENT_SUCCEEDED = 'invoice.payment.succeeded';

    const INVOICE_PAYMENT_FAILED = 'invoice.payment.failed';

    const INVOICEITEM_CREATED = 'invoiceitem.created';

    const INVOICEITEM_UPDATED = 'invoiceitem.updated';

    const INVOICEITEM_DELETED = 'invoiceitem.deleted';

    const PLAN_CREATED = 'plan.created';

    const PLAN_UPDATED = 'plan.updated';

    const PLAN_DELETED = 'plan.deleted';

    const COUPON_CREATED = 'coupon.created';

    const COUPON_DELETED = 'coupon.deleted';

    const TRANSFER_CREATED = 'transfer.created';

    const TRANSFER_UPDATED = 'transfer.updated';

    const TRANSFER_PAID = 'transfer.paid';

    const TRANSFER_FAILED = 'transfer.failed';
} 