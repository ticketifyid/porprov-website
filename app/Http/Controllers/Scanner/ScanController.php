<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(): View
    {
        return view('scanner.index');
    }
}
