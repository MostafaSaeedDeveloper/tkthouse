<?php

namespace Tests\Unit;

use App\Services\Checkout\OrderPlacementService;
use PHPUnit\Framework\TestCase;

class OrderItemSnapshotNameTest extends TestCase
{
    public function test_normal_names_are_untouched_and_the_worst_case_fits_the_255_character_column(): void
    {
        $this->assertSame('Neon Night - General', OrderPlacementService::snapshotName('Neon Night', 'General'));

        $both255 = OrderPlacementService::snapshotName(str_repeat('E', 255), str_repeat('T', 255));
        $this->assertSame(255, mb_strlen($both255));

        $arabic = OrderPlacementService::snapshotName(str_repeat('ح', 255), str_repeat('ت', 255));
        $this->assertSame(255, mb_strlen($arabic), 'multibyte safe');
    }
}
