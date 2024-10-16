<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankController extends Controller
{
    public function index()
    {

        $user = Auth::user();
        $bankAccounts = $user->bankAccounts;
        $result = [];

        foreach ($bankAccounts as $account) {
            $result[] = [
                'id' => $account->bank->id,
                'name' => $account->bank->name,
            ];
        }

        return response()->json($result);
    }   
}
