<?php

namespace App\Http\Controllers;

use App\Models\BusinessContact;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact', ['contact' => BusinessContact::current()]);
    }
}
