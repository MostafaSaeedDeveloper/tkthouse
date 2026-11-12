<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * OTHER LAND — A FRACTURE FESTIVAL (31 Dec 2026 – 01 Jan 2027, Le Lac Du Caire).
 *
 * Standalone and NOT registered in DatabaseSeeder, so it never runs as part of a normal `db:seed`:
 *
 *     php artisan db:seed --class=OtherLandEventSeeder
 *
 * Safe to re-run: it only touches the "other-land" event (matched by slug) and its own tickets / lineup.
 * Tickets and lineup rows are updated in place (matched by name), never deleted, so orders already placed
 * against a ticket type keep pointing at it. Other events, orders, users and settings are not touched.
 */
class OtherLandEventSeeder extends Seeder
{
    private const SLUG = 'other-land';

    public function run(): void
    {
        $event = Event::updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'OTHER LAND',
                'event_date' => '2026-12-31',
                'end_date' => '2027-01-01',
                'event_time' => '20:00:00',
                'location' => 'Le Lac Du Caire, Cairo, Egypt',
                'map_url' => 'https://maps.app.goo.gl/18g6Gv1RaTj6VxvP6?g_st=ic',
                'map_embed' => '<iframe src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3454.923794420717!2d31.247079875552384!3d30.010344474941043!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2zMzDCsDAwJzM3LjIiTiAzMcKwMTQnNTguOCJF!5e0!3m2!1sen!2seg!4v1794527568525!5m2!1sen!2seg" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>',
                'description' => $this->description(),
                'house_rules' => $this->houseRules(),
                'cover_image' => 'uploads/events/other-land-cover.jpg',
                'event_banner' => 'uploads/events/banners/other-land-banner.jpg',
                'status' => 'active',
            ]
        );

        $this->syncTickets($event);
        $this->syncLineup($event);
    }

    private function syncTickets(Event $event): void
    {
        $dayOne = 'Day 01 Pass — 31 December';
        $dayTwo = 'Day 02 Pass — 01 January';
        $both = '2 Days Pass — 31 December + 01 January';

        $coupleNote = 'For Couples — 2 Persons (price per person, sold as 1 couple)';

        // [category, pass, price per person (EGP), is_couple]
        // Regular Couples — 2 Days Pass is intentionally absent: no price was supplied. Add it here once confirmed.
        $rows = [
            ['Regular Female', $dayOne, 1500, false],
            ['Regular Male', $dayOne, 2000, false],
            ['Regular Couples', $dayOne, 1500, true],
            ['Backstage Female', $dayOne, 3000, false],
            ['Backstage Couples', $dayOne, 2500, true],

            ['Regular Female', $dayTwo, 1500, false],
            ['Regular Male', $dayTwo, 2000, false],
            ['Regular Couples', $dayTwo, 1500, true],
            ['Backstage Female', $dayTwo, 3000, false],
            ['Backstage Couples', $dayTwo, 2500, true],

            ['Regular Female', $both, 2000, false],
            ['Regular Male', $both, 3000, false],
            ['Backstage Female', $both, 3000, false],
            ['Backstage Couples', $both, 3000, true],
        ];

        foreach ($rows as $i => [$category, $pass, $price, $isCouple]) {
            $backstage = str_starts_with($category, 'Backstage');

            $event->tickets()->updateOrCreate(
                ['name' => "{$category} — {$pass}"],
                [
                    'price' => $price,
                    'status' => 'active',
                    'color' => $backstage ? '#c1121f' : '#212529',
                    'label' => $backstage ? 'Backstage' : 'Regular',
                    'description' => $isCouple ? $coupleNote : 'Single attendee',
                    'is_couple' => $isCouple,
                    'max_per_order' => $isCouple ? 2 : 10,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    private function syncLineup(Event $event): void
    {
        // performance_time / performance_date are left empty: no set times were supplied. Fill them in from the admin event form.
        $artists = [
            ['Space Motion', 'spacemotionmusic'],
            ['Woo York', 'rafaelcerato_ofc'],
        ];

        foreach ($artists as $i => [$name, $instagram]) {
            $event->lineups()->updateOrCreate(
                ['artist_name' => $name],
                ['instagram' => $instagram, 'sort_order' => $i + 1]
            );
        }
    }

    private function description(): string
    {
        return <<<'TXT'
OTHER LAND — A FRACTURE FESTIVAL

Two Days. One Destination. Another World.

This New Year’s weekend, FRACTURE invites you to escape reality and enter OTHER LAND — an extraordinary two-day electronic music festival where music, art, and imagination collide.

Set against the stunning waterfront of Le Lac Du Caire, OTHER LAND brings together powerful electronic sounds, immersive stage designs, breathtaking visuals, and next-level production to create an experience beyond the ordinary.

As we say goodbye to 2026 and welcome 2027, prepare for two unforgettable days of music, energy, and connection in a world built to take you somewhere else.

This isn’t just another festival. It’s a journey into another dimension.

December 31, 2026 – January 1, 2027
Le Lac Du Caire, Cairo, Egypt
Entry time: 8 PM

FRACTURE PRESENTS: OTHER LAND

Leave reality behind. Enter OTHER LAND.
TXT;
    }

    private function houseRules(): string
    {
        return implode("\n", [
            'TICKET CATEGORIES: Entry is permitted for individual female guests, individual male guests, and couples according to their purchased ticket category.',
            'COUPLES TICKETS: Couples tickets are valid for two attendees and require both guests to enter together.',
            'VALID ID REQUIRED: All attendees must present a valid original national ID or passport.',
            'TICKET VALIDITY: Day 01 Pass is valid for December 31, Day 02 Pass for January 1, and 2 Days Pass for both days.',
            'NO REFUNDS OR TRANSFERS: All ticket purchases are final and non-transferable.',
            'NO OUTSIDE FOOD OR DRINKS: Outside food and beverages are not permitted.',
            'PROHIBITED ITEMS: Weapons, illegal substances, fireworks, and dangerous items are strictly prohibited.',
            'RESPECT EVERYONE: Harassment, violence, discrimination, and aggressive behavior will not be tolerated.',
            'SECURITY CHECKS: All guests are subject to security screening.',
            'BACKSTAGE ACCESS: Backstage areas are exclusively for valid Backstage Pass holders.',
            'PERSONAL BELONGINGS: Guests are responsible for their belongings.',
            'PHOTO & VIDEO: Event photography and videography will take place throughout the festival.',
            'FOLLOW STAFF INSTRUCTIONS: All guests must comply with security and event management instructions.',
        ]);
    }
}
