<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankAccountController extends Controller
{
    public function getBankAccountDetail($bankId)
    {
        $user = Auth::user();
        $bankAccounts = $user->bankAccounts;

        $bankAccount = $bankAccounts->where('bank_id', $bankId)->first();

        if ($bankAccount) {
            return response()->json([
                'account_id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->account_number,
            ]);
        } else {
            return response()->json(['message' => 'Bank account not found'], 404);
        }

        
    }
}
