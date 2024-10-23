<?php

namespace App\Http\Controllers;

use App\Models\Bank;
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
    
    public function bank(){
        $bank = Bank::all();
        return response()->json([
            'data' => $bank
        ]);
    }

    public function show($id){
        $bank = Bank::find($id)->first();
        return response()->json([
            'data' => $bank
        ]);
    }

    public function store(Request $request){
        Bank::create([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => "success create bank"
        ]);
    }


    public function update(Request $request, $id){
       
        $Bank = Bank::find($id);

        if(!$Bank){
            return response()->json([
                'data' => "Bank not found"
            ]); 
        }
        $Bank->update([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => "success create bank"
        ]);
    }


    public function delete(Request $request, $id){
       
        $Bank = Bank::find($id);

        if(!$Bank){
            return response()->json([
                'data' => "Bank not found"
            ]); 
        }
        $Bank->delete();

        return response()->json([
            'data' => "success delete bank"
        ]);
    }
}
