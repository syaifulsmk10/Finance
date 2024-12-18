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
use Illuminate\Support\Facades\Log;
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

    // Convert user data to array and add full image URL
    $userData = $user->toArray();
    if ($user->path) {
        $userData['path'] = url('uploads/profiles/' . $user->path);
    }

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
    
        $query = User::with(['role', 'position', 'department', 'staff.manager', 'staff.staffMember'])
            ->whereHas('role', function ($q) {
                $q->where('name', 'Employee');
            });
            
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
                    });
            });
        }
    
        // Tambahkan orderBy untuk mengurutkan berdasarkan ID
        $users = $query->orderBy('id')->paginate($perPage);
    
        return response()->json([
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }
    

public function store(Request $request)
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'position_id' => 'required|integer',
        'department_id' => 'nullable',
        'name' => 'required',
        'username' => 'required|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'level' => 'sometimes|integer',
        'path.*' => 'required|file|image|max:2048',
    ]);

    // Validasi tambahan untuk manager_id berdasarkan position_id
    $validator->after(function ($validator) use ($request) {
        if (in_array($request->position_id, [1, 2, 3, 4]) && $request->filled('manager_id')) {
            $validator->errors()->add('manager_id', 'Manager ID should not be filled for the selected position.');
        } elseif (!in_array($request->position_id, [1, 2, 3, 4]) && !$request->filled('manager_id')) {
            $validator->errors()->add('manager_id', 'Manager ID is required for the selected position.');
        }
    });

    // Cek jika validasi gagal
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation Error',
            'errors' => $validator->errors()
        ], 422);
    }

    // Cek apakah file 'path' ada
    if (!$request->hasFile('path')) {
        return response()->json([
            'message' => 'path cant be null',
        ], 422);
    }

    // Proses upload file
    $image = $request->file('path');
    $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalName();
    $image->move(public_path('uploads/profiles'), $imageName);
    $imagePath = $imageName;

    // Buat user baru
    $user = User::create([
        'role_id' => 5,
        'position_id' => $request->position_id,
        'department_id' => $request->department_id,
        'name' => $request->name,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'path' => $imagePath
    ]);

    // Tambahkan data staff hanya jika manager_id diperlukan
    if ($request->filled('manager_id')) {
        Staff::create([
            'manager_id' => $request->manager_id,
            'staff_id' => $user->id
        ]);
    }

    // Return response
    return response()->json(['message' => 'User created successfully'], 201);
}


  

  public function show($id)
  {
      $user = User::with(['role', 'position', 'department', 'staff.manager',  'staff.staffMember'])->findOrFail($id);
  
   
      if ($user->path) {
          $user->path = url('uploads/profiles/' . $user->path);
      }
  
      return response()->json([
          'data' => $user,
          'message' => 'User retrieved successfully'
      ]);
  }
  


  public function update(Request $request, $id)
  {
      // Ambil user yang akan diperbarui
      $user = User::find($id); // Gunakan find() agar tidak melempar exception jika tidak ditemukan
  
      if (!$user) {
          return response()->json(['message' => 'User not found'], 404); // Jika user tidak ditemukan, kembalikan error 404
      }
  
      // Validasi input
      $validator = Validator::make($request->all(), [
          'position_id' => 'sometimes|required|integer',
          'department_id' => 'nullable',
          'name' => 'sometimes|required',
          'username' => 'sometimes|required|unique:users,username,' . $id,
          'email' => 'sometimes|required|email|unique:users,email,' . $id,
          'password' => 'sometimes|nullable|min:6',
          'level' => 'sometimes|integer',
          'path.*' => 'sometimes|file|image|max:2048',
          'manager_id' => 'nullable|integer', // Pastikan manager_id nullable
      ]);
  
      // Validasi tambahan untuk manager_id berdasarkan position_id
      $validator->after(function ($validator) use ($request, $user) {
          // Gunakan position_id lama jika tidak ada di request
          $positionId = $request->position_id ?? $user->position_id;
  
          if (in_array($positionId, [1, 2, 3, 4])) {
              if ($request->filled('manager_id')) {
                  $validator->errors()->add('manager_id', 'Manager ID should not be filled for the selected position.');
              }
          } else {
              // Jika position_id tidak termasuk 1, 2, 3, atau 4, manager_id harus diisi
              if (!$request->filled('manager_id')) {
                  $validator->errors()->add('manager_id', 'Manager ID is required for the selected position.');
              }
          }
      });
  
      if ($validator->fails()) {
          return response()->json([
              'message' => 'Validation Error',
              'errors' => $validator->errors()
          ], 422);
      }
  
      if ($request->has('position_id')) {
          $user->position_id = $request->position_id;
      }
      if ($request->has('department_id')) {
          $user->department_id = $request->department_id;
      }elseif ($user->department_id) {
        $user->department_id = null; 
    } else {
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
      if ($request->has('password') && !empty($request->password)) {
          $user->password = Hash::make($request->password);
      }
  
      // Proses upload file jika ada
      if ($request->hasFile('path')) {
          $image = $request->file('path');
          $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
          $image->move(public_path('uploads/profiles'), $imageName);
          $user->path = $imageName;
      }
  
      $user->save();
  
      // Update staff terkait
      $staff = Staff::where('staff_id', $user->id)->first();
      
  
      if ($staff) {
        // Cek posisi apakah perlu direset manager_id
        if (in_array($request->position_id, [1, 2, 3, 4]) || in_array($user->position_id, [1, 2, 3, 4])) {
            $staffToDelete = Staff::where('staff_id', $user->id)->first();
            if ($staffToDelete) {
                $staffToDelete->delete();
            }
        } elseif ($request->filled('manager_id')) {
            // Set manager_id jika ada
            $staff->manager_id = $request->manager_id;
        }
    
        // Simpan perubahan jika ada
        $staff->save();
    } else {
        // Jika staff belum ada
        if (in_array($request->position_id, [1, 2, 3, 4]) || in_array($user->position_id, [1, 2, 3, 4])) {
            // Tidak perlu menambah staff, hanya simpan posisi
            // Tidak ada operasi untuk tabel `staff`
        } elseif ($request->filled('manager_id')) {
            // Jika perlu membuat staff baru
            $staff = new Staff();
            $staff->staff_id = $user->id;
            $staff->manager_id = $request->manager_id;
    
            // Simpan staff baru
            $staff->save();
        }
    }
    
      // Return response
      return response()->json(['message' => 'User updated successfully'], 200);
  }
  
  






public function destroy($id)
{
    $user = User::findOrFail($id);


    if ($user->path && file_exists(public_path('uploads/profiles/' . $user->path))) {
        unlink(public_path('uploads/profiles/' . $user->path));
    }


    Staff::where('staff_id', $user->id)->delete();

    $user->delete();

    return response()->json(['message' => 'User deleted successfully'], 200);
}


public function updateprofiles(Request $request)
{

    $validator = Validator::make($request->all(), [
       'name' => 'nullable|string|max:255',
        'username' => 'nullable|string|max:255|unique:users,username,' . Auth::user()->id,
        'email' => 'nullable|email|max:255|unique:users,email,' . Auth::user()->id,
        'password' => 'nullable|string|min:8|confirmed',
        'path' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    // Ambil user berdasarkan ID
    $user = User::where('id', Auth::user()->id)->first();

    // Update profil
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
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension(); 
        $image->move(public_path('uploads/profiles'), $imageName);
        $user->path = $imageName;
    }

    $user->save();


    return response()->json([
        'message' => 'Profile updated successfully'
    ]);
}






public function updateprofilesadmin(Request $request){

    $validator = Validator::make($request->all(), [
        'name' => 'nullable|string|max:255',
        'username' => 'nullable|string|max:255|unique:users,username,' . Auth::user()->id,
        'email' => 'nullable|email|max:255|unique:users,email,' . Auth::user()->id,
        'password' => 'nullable|min:8|confirmed',
        'path' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', 
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

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
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('uploads/profiles'), $imageName);
        $user->path = $imageName;
    }

    $user->save();

    return response()->json([
        'message' => 'Profile updated successfully'
    ]);
}

public function getProfileadmin()
{
    $user = Auth::user();


    $user->load('position', 'department');
    $imageUrl = $user->path ? url('uploads/profiles/' . $user->path) : null;

    return response()->json([
        'user' => $user,
        'profile_image_url' => $imageUrl
    ]);
}

public function getProfile()
{
    $user = Auth::user();
    $user->load('position', 'department');
    $imageUrl = $user->path ? url('uploads/profiles/' . $user->path) : null;

    return response()->json([
        'user' => $user,
        'profile_image_url' => $imageUrl
    ]);
}







}
