<?php

namespace App\Http\Controllers;

use App\Models\FixedDeposit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedDepositController extends Controller
{
    public function index()
    {
        $deposits = FixedDeposit::orderByDesc('id')->get();
        return Inertia::render('FixedDeposits/Index', [
            'deposits' => $deposits,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank' => 'required|string',
            'accountno' => 'required|string',
            'principal_amt' => 'required|numeric',
            'maturity_amt' => 'required|numeric',
            'start_date' => 'required|date',
            'maturity_date' => 'required|date',
            'term' => 'required|integer',
            'int_rate' => 'required|numeric',
            'Int_amt' => 'required|numeric',
            'Int_year' => 'required|numeric',
        ]);
        FixedDeposit::create($data);
        return redirect()->route('fixed-deposits');
    }
}
