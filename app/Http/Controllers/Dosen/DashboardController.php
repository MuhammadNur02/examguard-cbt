<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $exams = $request->user()->exams()
            ->withCount('questions')
            ->orderByDesc('mulai')
            ->get();

        return view('dosen.dashboard', ['exams' => $exams]);
    }
}
