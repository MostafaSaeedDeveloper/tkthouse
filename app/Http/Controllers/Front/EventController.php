<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventController extends Controller
{
    public function home() { return view('front.home', ['events' => Event::where('status', 'published')->latest()->take(6)->get()]); }
    public function index() { return view('front.events.index', ['events' => Event::where('status', 'published')->paginate(12)]); }
    public function show(string $slug) { return view('front.events.show', ['event' => Event::whereSlug($slug)->with('ticketTypes')->firstOrFail()]); }
}
