<?php

namespace App\Http\Controllers;

use App\Models\BankBalance;
use App\Models\BankAccount;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankBalanceController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'update_date' => 'required|date',
            'bank_balances' => 'required|array|min:1',
            'bank_balances.*.bank_id' => 'required|exists:banks,id',
            'bank_balances.*.account_number' => 'required|string|max:255',
            'bank_balances.*.balance' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->bank_balances as $balanceData) {
                BankBalance::create([
                    'user_id' => Auth::id(),
                    'bank_id' => $balanceData['bank_id'],
                    'account_number' => $balanceData['account_number'],
                    'balance' => $balanceData['balance'],
                    'update_date' => $request->update_date,
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Bank balances updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'general' => 'An error occurred while saving bank balances. Please try again.'
            ]);
        }
    }

    public function getAccountsByBank($bankId)
    {
        $accounts = BankAccount::where('bank_id', $bankId)->get();
        return response()->json($accounts);
    }

    public function storeAccount(Request $request)
    {
        $request->validate([
            'bank_id' => 'required|exists:banks,id',
            'account_number' => 'required|string|max:255',
            'account_type' => 'nullable|string|max:255',
        ]);

        try {
            $account = BankAccount::create([
                'bank_id' => $request->bank_id,
                'account_number' => $request->account_number,
                'account_type' => $request->account_type,
            ]);

            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Account added successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding the account. Please try again.'
            ], 500);
        }
    }
}
