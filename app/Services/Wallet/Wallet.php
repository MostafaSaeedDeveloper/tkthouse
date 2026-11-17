<?php

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\IssuedTicket;

/** The one entry point for "Add to Apple Wallet": picks the provider chosen in Settings → Apple Wallet. */
final class Wallet
{
    public static function configured(): bool
    {
        return WalletSettings::provider() === 'walletwallet' ? WalletWalletClient::configured() : ApplePassBuilder::configured();
    }

    /** @return string path of a temporary .pkpass file */
    public static function build(IssuedTicket $ticket, ?Event $event): string
    {
        return WalletSettings::provider() === 'walletwallet'
            ? app(WalletWalletClient::class)->build($ticket, $event)
            : app(ApplePassBuilder::class)->build($ticket, $event);
    }
}
