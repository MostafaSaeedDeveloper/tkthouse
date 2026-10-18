<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\Routing\OfferedMethods;
use App\Services\Payments\Routing\Resolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** "Default" payment methods: what customers see when no agent-level selection narrows the list. */
class PaymentMethodDefaultTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true]);
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        PaymentMethod::query()->update(['is_active' => false]); // methods inserted by migrations
        foreach (['fake_card', 'fake_wallet', 'fake_apple'] as $code) {
            $this->makeMethod('fake', $code);
        }
    }

    private function codes(?array $allowed = null): array
    {
        return app(OfferedMethods::class)->for(new Resolution(null, 'legacy', allowedMethods: $allowed))->pluck('code')->sort()->values()->all();
    }

    private function setDefault(string $code, bool $on = true): void
    {
        PaymentMethod::where('code', $code)->update(['is_default' => $on]);
    }

    public function test_with_no_default_flagged_every_active_method_is_offered_as_before(): void
    {
        $this->assertSame(['fake_apple', 'fake_card', 'fake_wallet'], $this->codes());
    }

    public function test_only_default_methods_are_offered_when_nothing_else_narrows_the_list(): void
    {
        $this->setDefault('fake_card');
        $this->setDefault('fake_wallet');

        $this->assertSame(['fake_card', 'fake_wallet'], $this->codes());
    }

    public function test_an_agent_selection_wins_over_the_defaults(): void
    {
        $this->setDefault('fake_card');

        $this->assertSame(['fake_apple', 'fake_wallet'], $this->codes(['fake_wallet', 'fake_apple']));
    }

    public function test_a_disabled_default_is_ignored_and_never_leaves_the_customer_without_a_method(): void
    {
        $this->setDefault('fake_card');
        PaymentMethod::where('code', 'fake_card')->update(['is_active' => false]);
        $this->assertSame(['fake_apple', 'fake_wallet'], $this->codes(), 'no active default => nothing is narrowed');
    }

    public function test_defaults_that_are_unusable_right_now_fall_back_to_all_available_methods(): void
    {
        // A Paymob method without credentials cannot take a payment, so a customer offered only that default could never pay.
        PaymentMethod::where('code', 'fake_card')->update(['provider' => 'paymob', 'config' => [], 'is_default' => true]);

        $this->assertSame(['fake_apple', 'fake_wallet'], $this->codes());
    }

    public function test_the_default_flag_is_saved_from_the_form_and_shown_in_the_list(): void
    {
        foreach (['payment-methods.view', 'payment-methods.create', 'payment-methods.update'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = tap(User::factory()->create(), fn ($u) => $u->givePermissionTo(['payment-methods.view', 'payment-methods.create', 'payment-methods.update']));
        $method = PaymentMethod::where('code', 'fake_card')->firstOrFail();

        $this->actingAs($u)->put(route('admin.payment-methods.update', $method), ['name' => $method->name, 'code' => 'fake_card', 'provider' => 'manual', 'is_active' => 1, 'is_default' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($method->fresh()->is_default);

        $page = $this->actingAs($u)->get(route('admin.payment-methods.index'))->assertOk();
        $page->assertSee('Default')->assertDontSee('Merchant accounts')->assertDontSee('Legacy configuration only')->assertDontSee('In use');

        $this->actingAs($u)->put(route('admin.payment-methods.update', $method), ['name' => $method->name, 'code' => 'fake_card', 'provider' => 'manual', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($method->fresh()->is_default);
    }
}
