<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Display the document management page.
     */
    public function index(): View
    {
        return view('pages.documents.index');
    }
}