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

    public function eventShow()
    {
        return view('front.events.show');
    }

    public function contact()
    {
        return view('front.contact');
    }
}
