<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventsSeeder extends Seeder
{
    public function run(): void
    {
        $eventsData = [
            [
                'name' => 'Sideral',
                'event_date' => now()->addWeeks(3)->toDateString(),
                'event_time' => '21:00:00',
                'location' => 'Cairo, Egypt',
                'map_url' => null,
                'description' => 'Sideral event seeded from ticket-easy reference source.',
                'house_rules' => 'No outside food or drinks. Respect venue rules. 18+ entry.',
                'status' => 'active',
                'tickets' => [
                    ['name' => 'Early Bird', 'price' => 450, 'status' => 'active', 'label' => 'Limited', 'description' => 'Limited quantity'],
                    ['name' => 'Regular', 'price' => 650, 'status' => 'active', 'label' => 'General', 'description' => 'General admission'],
                ],
                'fees' => [
                    ['name' => 'Service Fee', 'fee_type' => 'percentage', 'value' => 10, 'description' => 'Platform service fee'],
                    ['name' => 'Processing Fee', 'fee_type' => 'fixed', 'value' => 10, 'description' => 'Per-ticket processing fee'],
                ],
            ],
            [
                'name' => 'Pulsed',
                'event_date' => now()->addWeeks(5)->toDateString(),
                'event_time' => '22:00:00',
                'location' => 'Alexandria, Egypt',
                'map_url' => null,
                'description' => 'Pulsed event seeded from ticket-easy reference source.',
                'house_rules' => 'No re-entry. Keep your ticket QR available at gate.',
                'status' => 'active',
                'tickets' => [
                    ['name' => 'Phase 1', 'price' => 500, 'status' => 'active', 'label' => 'Phase 1', 'description' => 'First release'],
                    ['name' => 'Phase 2', 'price' => 750, 'status' => 'active', 'label' => 'Phase 2', 'description' => 'Second release'],
                    ['name' => 'Door', 'price' => 900, 'status' => 'inactive', 'label' => 'Gate', 'description' => 'Sold at gate only'],
                ],
                'fees' => [
                    ['name' => 'Service Fee', 'fee_type' => 'percentage', 'value' => 10, 'description' => 'Platform service fee'],
                    ['name' => 'Handling Fee', 'fee_type' => 'fixed', 'value' => 10, 'description' => 'Per-ticket handling fee'],
                ],
            ],
        ];

        foreach ($eventsData as $item) {
            $event = Event::updateOrCreate(
                ['name' => $item['name']],
                [
                    'event_date' => $item['event_date'],
                    'event_time' => $item['event_time'],
                    'location' => $item['location'],
                    'map_url' => $item['map_url'],
                    'description' => $item['description'],
                    'house_rules' => $item['house_rules'],
                    'status' => $item['status'],
                ]
            );

            $event->tickets()->delete();
            foreach ($item['tickets'] as $ticket) {
                $event->tickets()->create($ticket);
            }

            $event->fees()->delete();
            foreach ($item['fees'] as $fee) {
                $event->fees()->create($fee);
            }
        }
    }
}
