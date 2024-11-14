<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function postLogin(Request $request)
    {
        $validate = $request->validate([
            "email" => 'required|email',
            "password" => "required",
        ]);

        if (!Auth::attempt($validate)) {
            return response()->json([
                'message' => 'Wrong email or password',
                'data' => $validate
            ], 404);
        }


        $user = Auth::user();
        $token = $user->createToken('auth')->plainTextToken;
        $userData = $user->toArray(); //

        if ($user->role_id == 1) {
            return response()->json([
                'message' => 'Success Login Admin',
                'data' => $userData,
                'token' => $token
            ], 200);
        }

        return response()->json([
            'message' => 'Success Login User',
            'data' => $userData,
            'token' => $token
        ], 200);
    }


    public function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        // Check if the validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('role_id', 2)->get();
        foreach ($user as $users) {
            if ($users->email == $request->email) {
                return response()->json([
                    "message" => "email is already in use"
                ]);
            }
        }

        $user = User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "role_id" => 2,
            'position_id' => 3,
            "path" => "admin.png",
            "username" => $request->name,
            "level" => 0,
        ]);

        

        return response()->json([
            'message' => 'success register admin',
            'data' => $user
        ], 200);
    }


 
    public function index(Request $request)
{
    $search = $request->input('search');
    $perPage = $request->input('per_page', 10); 


    $query = User::with(['role', 'position', 'bankAccounts', 'bankAccounts.bank', 'department', 'staff.manager', 'staff.staffMember']);
         

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")  
                ->orWhere('nip', 'LIKE', "%{$search}%") 
                ->orWhere('email', 'LIKE', "%{$search}%") 
                ->orWhereHas('department', function ($q) use ($search) { 
                    $q->where('name', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('position', function ($q) use ($search) {  
                    $q->where('name', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('bankAccounts', function ($q) use ($search) { 
                    $q->where('account_name', 'LIKE', "%{$search}%") 
                        ->orWhere('account_number', 'LIKE', "%{$search}%") 
                        ->orWhereHas('bank', function ($q) use ($search) { 
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                });
        });
    }

    $users = $query->paginate($perPage);

    return response()->json([
        'data' => $users, 'message' => 'Users retrieved successfully'
    ]);
}


  public function store(Request $request)
  {
      $validator = Validator::make($request->all(), [
          'position_id' => 'required',
          'department_id' => 'required',
          'name' => 'required',
          'username' => 'required|unique:users',
          'email' => 'required|email|unique:users',
          'password' => 'required|min:6',
          'level' => 'sometimes|integer', 
          'path.*' => 'required|file|image|max:2048',
          'bank.*.bank_id' => 'required|integer',  // validate nested bank_id
          'bank.*.account_name' => 'required|string',
          'bank.*.account_number' => 'required|string'
      ]);
  
      if ($validator->fails()) {
          return response()->json([
              'message' => 'Validation Error',
              'errors' => $validator->errors()
          ], 422);
      }
  
      if (!$request->hasFile('path')) {
          return response()->json([
              'message' => 'path cant be null',
          ], 200);
      }
  
      $image = $request->file('path');
      $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalName();
      $image->move(public_path('uploads/profiles'), $imageName);
      $imagePath = $imageName;
  
      $user = User::create([
          'role_id' => 2,
          'position_id' => $request->position_id,
          'department_id' => $request->department_id,
          'name' => $request->name,
          'username' => $request->username,
          'email' => $request->email,
          'password' => Hash::make($request->password),
          'path' => $imagePath
      ]);
  
      foreach ($request->bank as $bank) {
          BankAccount::create([
              'user_id' => $user->id,
              'bank_id' => $bank['bank_id'], // Access bank_id from nested array
              'account_name' => $bank['account_name'],
              'account_number' => $bank['account_number']
          ]);
      }
  
      Staff::create([
          'manager_id' => $request->manager_id,
          'staff_id' => $user->id
      ]);
  
      return response()->json(['message' => 'User created successfully'], 201);
  }
  

    public function show($id)
    {

        $user = User::with(['role', 'position', 'bankAccounts', 'bankAccounts.bank', 'department', 'staff.manager',  'staff.staffMember'])->findOrFail($id);

        return response()->json([
            'data' => $user, 'message' => 'User retrieved successfully'
        ]);
    }



public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'position_id' => 'sometimes|required',
        'department_id' => 'sometimes|required',
        'name' => 'sometimes|required',
        'username' => 'sometimes|required|unique:users,username,' . $id,
        'email' => 'sometimes|required|email|unique:users,email,' . $id,
        'password' => 'sometimes|nullable|min:6',
        'level' => 'sometimes|integer',
        'path.*' => 'sometimes|file|image|max:2048',
        'bank.*.bank_id' => 'sometimes|required',
        'bank.*.account_name' => 'sometimes|required',
        'bank.*.account_number' => 'sometimes|required',
        'manager_id' => 'sometimes|required'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation Error',
            'errors' => $validator->errors()
        ], 422);
    }

    $user = User::findOrFail($id);

    if ($request->has('position_id')) {
        $user->position_id = $request->position_id;
    }
    if ($request->has('department_id')) {
        $user->department_id = $request->department_id;
    }
    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('username')) {
        $user->username = $request->username;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }
    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }

    if ($request->hasFile('path')) {
        $image = $request->file('path');
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalName();
        $image->move(public_path('uploads/profiles'), $imageName);
        $user->path = $imageName;
    }

    $user->save();

    if ($request->has('bank')) {
        $bankIdsToKeep = [];

        foreach ($request->bank as $bankData) {
            if (isset($bankData['id'])) {
                // Update existing bank account fields that are present in the request
                $bankAccount = BankAccount::where('user_id', $user->id)
                    ->where('id', $bankData['id'])
                    ->first();

                if ($bankAccount) {
                    // Update only fields that are included in the request
                    $bankAccount->update(array_filter([
                        'bank_id' => $bankData['bank_id'] ?? null,
                        'account_name' => $bankData['account_name'] ?? null,
                        'account_number' => $bankData['account_number'] ?? null,
                    ]));

                    $bankIdsToKeep[] = $bankAccount->id;
                }
            } else {
                // Add new bank account
                $newBankAccount = BankAccount::create([
                    'user_id' => $user->id,
                    'bank_id' => $bankData['bank_id'],
                    'account_name' => $bankData['account_name'],
                    'account_number' => $bankData['account_number']
                ]);
                $bankIdsToKeep[] = $newBankAccount->id;
            }
        }

        // Delete bank accounts that are not in the current request
        BankAccount::where('user_id', $user->id)
            ->whereNotIn('id', $bankIdsToKeep)
            ->delete();
    }

    $staff = Staff::where('staff_id', $user->id)->first();
    
    if ($request->has('manager_id')) {
        $staff->manager_id = $request->manager_id;
    }

    $staff->save();

    return response()->json(['message' => 'User updated successfully'], 200);
}






public function destroy($id)
{
    $user = User::findOrFail($id);


    if ($user->path && file_exists(public_path('uploads/profiles/' . $user->path))) {
        unlink(public_path('uploads/profiles/' . $user->path));
    }

    BankAccount::where('user_id', $user->id)->delete();

    Staff::where('staff_id', $user->id)->delete();

    $user->delete();

    return response()->json(['message' => 'User deleted successfully'], 200);
}


public function updateprofiles(Request $request)
{
    $user = User::where('id', Auth::user()->id)->first();

    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('username')) {
        $user->username = $request->username;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }
    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }

    if ($request->hasFile('path')) {
        $image = $request->file('path');
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalName();
        $image->move(public_path('uploads/profiles'), $imageName);
        $user->path = $imageName;
    }

    $user->save();

    // Update bank accounts
    if ($request->has('bank')) {
        $bankIdsToKeep = [];

        foreach ($request->bank as $bankData) {
            if (isset($bankData['id'])) {
                // Update existing bank account fields that are present in the request
                $bankAccount = BankAccount::where('user_id', $user->id)
                    ->where('id', $bankData['id'])
                    ->first();

                if ($bankAccount) {
                    // Update only fields that are included in the request
                    $bankAccount->update(array_filter([
                        'bank_id' => $bankData['bank_id'] ?? null,
                        'account_name' => $bankData['account_name'] ?? null,
                        'account_number' => $bankData['account_number'] ?? null,
                    ]));

                    $bankIdsToKeep[] = $bankAccount->id;
                }
            } else {
                // Add new bank account
                $newBankAccount = BankAccount::create([
                    'user_id' => $user->id,
                    'bank_id' => $bankData['bank_id'],
                    'account_name' => $bankData['account_name'],
                    'account_number' => $bankData['account_number']
                ]);
                $bankIdsToKeep[] = $newBankAccount->id;
            }
        }

        // Delete bank accounts that are not in the current request
        BankAccount::where('user_id', $user->id)
            ->whereNotIn('id', $bankIdsToKeep)
            ->delete();
    }

    return response()->json([
        'message' => 'update profile successfully'
    ]);
}




public function updateprofilesadmin(Request $request){
    $user = User::where('id', Auth::user()->id)->first();

    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('username')) {
        $user->username = $request->username;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }
    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }

    if ($request->hasFile('path')) {
        $image = $request->file('path');
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalName();
        $image->move(public_path('uploads/profiles'), $imageName);
        $user->path = $imageName;
    }

    $user->save();


   


    return response()->json([
        'message' => 'update profile successfully'
    ]);


}


public function getProfileadmin()
{
    // Mengambil data pengguna yang sedang login
    $user = Auth::user();

    // Memuat relasi 'position' dan 'department' jika dibutuhkan
    $user->load('position', 'department');

    // Membuat URL gambar profil jika ada
    $imageUrl = $user->path ? url('uploads/profiles/' . $user->path) : null;

    return response()->json([
        'user' => $user,
        'profile_image_url' => $imageUrl
    ]);
}

public function getProfile()
{
    // Mengambil data pengguna yang sedang login
    $user = Auth::user();

    // Memuat relasi 'position', 'department', dan 'bankAccounts'
    $user->load('position', 'department', 'bankAccounts.bank');

    // Membuat URL gambar profil jika ada
    $imageUrl = $user->path ? url('uploads/profiles/' . $user->path) : null;

    return response()->json([
        'user' => $user,
        'profile_image_url' => $imageUrl
    ]);
}







}
