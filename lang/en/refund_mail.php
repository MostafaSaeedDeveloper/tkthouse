<?php

/** Customer refund e-mails. Customer-facing: never name a payment provider, gateway, dashboard, API or internal note. */
return [
    'completed' => [
        'subject' => 'Your Refund Has Been Processed Successfully — TKT House',
        'hero_title' => 'Your Refund Has Been Processed',
        'hero_text' => 'Your refund has been processed successfully.',
        'intro' => 'Your refund for order <strong><bdi>#:order</bdi></strong> has been processed successfully.',
        'returned' => 'The refunded amount is being returned through the original payment method, where applicable.',
        'timing' => 'Please note that it may take some additional time for the refunded amount to appear in your original payment method, depending on your bank or payment provider.',
        'tickets_full' => 'The tickets included in this refund have been canceled and can no longer be used for entry.',
        'tickets_partial' => ':count ticket(s) included in this refund have been canceled and can no longer be used for entry. Tickets that were not included in the refund remain valid.',
        'tickets_none' => 'No tickets were canceled by this refund. Your tickets remain valid.',
        'footer' => 'This email was sent regarding a refund on your TKT House order.',
    ],
    'labels' => [
        'greeting' => 'Hi', 'there' => 'there', 'order' => 'Order', 'event' => 'Event', 'type' => 'Refund type', 'amount' => 'Refund amount', 'tickets' => 'Tickets included',
        'reason' => 'Reason', 'status' => 'Status', 'reference' => 'Refund reference', 'date' => 'Confirmation date',
        'full' => 'Full refund', 'partial' => 'Partial refund',
        'st_completed' => 'Completed',
    ],
    'contact' => 'If you have any questions, reply to this email and mention your order number <strong style="color:#dddde8;"><bdi>#:order</bdi></strong>.',
    'reason_fallback' => 'Refund arranged by our team',
];
