<?php

namespace Tests\Feature;

use App\Mail\AdminTicketIssuedMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminGuestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_guest_list_tickets(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.guest-list.store'), [
            'event_name' => 'My Event',
            'guest_type' => 'Regular',
            'guests' => [
                ['name' => 'Guest One', 'email' => 'guest1@example.com', 'phone' => '01000', 'gender' => 'male'],
                ['name' => 'Guest Two', 'email' => null, 'phone' => null],
            ],
        ]);

        $response->assertRedirect(route('admin.guest-list.index'));
        $this->assertSame(2, Ticket::query()->where('source', 'guest_list')->count());
        $this->assertDatabaseHas('tickets', [
            'holder_name' => 'Guest One',
            'guest_type' => 'Guest Regular',
            'source' => 'guest_list',
            'holder_gender' => 'male',
        ]);

        Mail::assertSent(AdminTicketIssuedMail::class, 1);
    }

    public function test_admin_can_import_guest_list_csv(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        $csv = implode("\n", [
            'guest_type,name,email,phone,gender,quantity',
            'VIP,Imported Guest,imported@example.com,01001,female,2',
        ]);

        $file = UploadedFile::fake()->createWithContent('guest-import.csv', $csv);

        $response = $this->actingAs($admin)->post(route('admin.guest-list.import'), [
            'event_name' => 'My Event',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertSame(2, Ticket::query()->where('source', 'guest_list')->count());
        $this->assertDatabaseHas('tickets', [
            'holder_name' => 'Imported Guest',
            'guest_type' => 'Guest VIP',
            'holder_gender' => 'female',
        ]);

        Mail::assertSent(AdminTicketIssuedMail::class, 2);
    }
}
