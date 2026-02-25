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

    public function events()
    {
        return view('front.events.index');
    }

    public function eventDetail(string $event = 'tech-future-summit')
    {
        return view('front.events.show', [
            'eventSlug' => $event,
        ]);
    }

    public function contact()
    {
        return view('front.contact');
    }
}
