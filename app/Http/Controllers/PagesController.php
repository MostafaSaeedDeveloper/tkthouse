<?php

namespace App\Http\Controllers;

class PagesController extends Controller
{
    public function home()
    {
        return view('front.index');
    }

    public function about()
    {
        return view('front.about');
    }

    public function contact()
    {
        return view('front.contact');
    }

    public function events()
    {
        return view('front.events.index');
    }

    public function event(string $event = 'launch-night')
    {
        return view('front.events.show', ['eventSlug' => $event]);
    }
}
