<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Event::query()->select('id', 'name')->orderBy('id')->chunkById(100, function ($events) {
            foreach ($events as $event) {
                $baseSlug = Str::slug($event->name);
                $baseSlug = $baseSlug !== '' ? $baseSlug : 'event';
                $slug = $baseSlug;
                $counter = 2;

                while (Event::query()->where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $event->updateQuietly(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
