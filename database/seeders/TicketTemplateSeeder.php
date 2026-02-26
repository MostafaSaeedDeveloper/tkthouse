<?php

namespace Database\Seeders;

use App\Models\TicketTemplate;
use Illuminate\Database\Seeder;

class TicketTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['name' => 'classic', 'view_key' => 'tickets.templates.classic'],
            ['name' => 'modern', 'view_key' => 'tickets.templates.modern'],
            ['name' => 'minimal', 'view_key' => 'tickets.templates.minimal'],
        ];

        foreach ($templates as $template) {
            TicketTemplate::updateOrCreate(['view_key' => $template['view_key']], $template + ['preview_image' => 'placeholder.png']);
        }
    }
}
