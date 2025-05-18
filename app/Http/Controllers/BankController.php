<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        return Bank::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:banks,name']);
        $bank = Bank::create(['name' => $request->name]);
        return response()->json($bank, 201);
    }
}