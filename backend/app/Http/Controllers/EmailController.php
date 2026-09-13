<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public static function sendEmail($email, $code)
    {
        Mail::to($email)->send(new \App\Mail\SignUp($code));
    }
}
