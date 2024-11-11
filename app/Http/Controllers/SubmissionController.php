<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\File;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubmissionController extends Controller
{
    public function index(Request $request) {
        $userId = Auth::user()->id;
    
        $query = Submission::with(['adminApprovals', 'files', 'items', 'user'])
                    ->where('user_id', $userId);
    
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('purpose', 'LIKE', "%{$search}%")
                ->orWhere('amount', 'LIKE', "%{$search}%");
        });
    
        if ($request->has('finish_status')) {
            $finishStatus = $request->input('finish_status');
            $query->where('finish_status', $finishStatus);
        }
    
        if ($request->has('type')) {
            $type = $request->input('type');
            $query->where('type', $type);
        }
    
        if ($request->has('due_date')) {
            $dueDate = $request->input('due_date');
            $query->whereDate('due_date', $dueDate);
        }
    
        $perPage = $request->input('per_page', 10); 
        $submissions = $query->paginate($perPage);
    
        // Tambahkan URL lengkap untuk setiap file
        $submissions->getCollection()->transform(function ($submission) {
            $submission->files->transform(function ($file) {
                // Pastikan file adalah array dengan memaksa decode JSON
                $fileArray = json_decode($file->file, true);
                
                if (is_array($fileArray)) {
                    // Buat array file_url jika file berbentuk array
                    $file->file_urls = collect($fileArray)->map(function ($fileName) {
                        return url('uploads/submission/' . $fileName);
                    })->toArray();
                } else {
                    // Jika file adalah string, tambahkan file_url tunggal
                    $file->file_url = url('uploads/submission/' . $file->file);
                }
    
                return $file;
            });
            return $submission;
        });
    
        return response()->json([
            'data' => $submissions
        ]);
    }
    
    


    public function detail($id) {
        $submission = Submission::with('adminApprovals', 'files', 'items', 'bankAccount.bank')
            ->where('user_id', Auth::user()->id)
            ->find($id);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }
    
        // Tambahkan URL lengkap untuk setiap file
        $submission->files->transform(function ($file) {
            // Decode JSON jika file dalam bentuk array JSON string
            $fileArray = json_decode($file->file, true);
    
            if (is_array($fileArray)) {
                // Jika file berbentuk array, buat daftar URL
                $file->file_urls = collect($fileArray)->map(function ($fileName) {
                    return url('uploads/submission/' . $fileName);
                })->toArray();
            } else {
                // Jika file adalah string tunggal, tambahkan satu URL
                $file->file_url = url('uploads/submission/' . $file->file);
            }
    
            return $file;
        });
    
        return response()->json([
            'data' => $submission
        ]);
    }
    
    

    public function store(Request $request)
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'type' => 'required|string',
        'purpose' => 'required|string',
        'due_date' => 'required|date',
        'submission_item' => 'required|array',
        'submission_item.*.description' => 'required|string',
        'submission_item.*.quantity' => 'required|integer|min:1',
        'submission_item.*.price' => 'required|numeric|min:0',
    ]);

    // Jika validasi gagal, kembalikan error
    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    $user = Auth::user();

    // Buat submission baru
    $submission = Submission::create([
        'user_id' => $user->id,
        'type' => $request->type,
        'purpose' => $request->purpose,
        'due_date' => $request->due_date,
        'submission_date' => Carbon::now(),
        'bank_account_id' => $request->bank_account_id,
        'finish_status' => 'process',
        'amount' => 0,
    ]);

    // Hitung total amount dan buat submission item
    $totalAmount = 0;
    foreach ($request->submission_item as $item) {
        SubmissionItem::create([
            'submission_id' => $submission->id,
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
        ]);

        // Jumlahkan total amount
        $totalAmount += $item['quantity'] * $item['price'];
    }

    // Update total amount pada submission
    $submission->amount = $totalAmount;
    $submission->save();

    // Simpan file jika ada
    if ($request->hasFile('file')) {
        $images = $request->file('file');
        $imagefiles = [];

        foreach ($images as $image) {
            $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/submission'), $imageName);
            $imagefiles[] = $imageName;
        }

        $type = in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'pdf';

        // Simpan data file ke tabel File
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode($imagefiles), 
            'type' =>  $type
        ]);
    }

    // Logika persetujuan AdminApproval berdasarkan posisi
    $positionName = $user->position->name;

    $approvalData = [
        'submission_id' => $submission->id,
        'status' => 'pending',
    ];

    // Tentukan user yang perlu memberikan approval berdasarkan posisi
    switch ($positionName) {
        case 'GA':
            $approvalData['user_id'] = 5;
            break;
        case 'Manager':
            $approvalData['user_id'] = 1;
            break;
        case 'CEO':
            $approvalData['user_id'] = 7;
            break;
        case 'Finance':
            // Jika posisi Finance, langsung set approved
            AdminApproval::create([
                'user_id' => $user->id,
                'submission_id' => $submission->id,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
            break;
        default:
        $approvalData['user_id'] = 6;
            
            break;
    }

   
    if ($positionName !== 'Finance') {
        AdminApproval::create($approvalData);
    }

    return response()->json(["message" => "Success Add submission"], 200);
}


    
    



public function update(Request $request, $id)
{
    $submission = Submission::findOrFail($id);

    // Cek status approval GA sebelum update
    $adminApprovalFirst = AdminApproval::where('submission_id', $submission->id)
        ->whereHas('user.role', function ($query) {
            $query->where('name', 'GA');
        })
        ->first();

    if ($adminApprovalFirst && $adminApprovalFirst->status !== 'pending') {
        return response()->json([ 
            "message" => "Submission cannot be updated because it has already been approved by the first admin (GA)."
        ], 403);
    }

    // Validasi input
    $validator = Validator::make($request->all(), [
        'type' => 'sometimes|string',
        'purpose' => 'sometimes|string',
        'due_date' => 'sometimes|date',
        'submission_item' => 'sometimes|array',
        'submission_item.*.description' => 'sometimes|string',
        'submission_item.*.quantity' => 'sometimes|integer|min:1',
        'submission_item.*.price' => 'sometimes|numeric|min:0',
        'file' => 'sometimes|array',
        'file.*' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:2048',
        'bank_account_id' => 'sometimes|integer|exists:bank_accounts,id',  // Validasi untuk bank_account_id
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Update data submission
    $submission->fill($request->only(['type', 'purpose', 'due_date', 'bank_account_id']));
    $totalAmount = $submission->amount;

    // Update submission items jika ada
    if ($request->has('submission_item')) {
        $totalAmount = 0;
        SubmissionItem::where('submission_id', $submission->id)->delete();

        foreach ($request->submission_item as $item) {
            SubmissionItem::create([
                'submission_id' => $submission->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
            $totalAmount += $item['quantity'] * $item['price'];
        }

        $submission->amount = $totalAmount;
    }

    $submission->save();

    // Update atau tambahkan file jika ada
   
    if ($request->has('file')) {
        $files = json_decode($request->input('file'));
        $imageFiles = [];
        $pdfFile = null;
    
        foreach ($files as $file) {
            // Mendapatkan ekstensi file
            $extension = pathinfo($file, PATHINFO_EXTENSION);
    
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                // Proses file gambar, masukkan ke array imageFiles
                $imageFiles[] = $file;
            } elseif ($extension === 'pdf') {
                // Proses file PDF, hanya satu file PDF yang bisa diterima
                $pdfFile = $file;
            }
        }
    
        // Tentukan tipe file berdasarkan file yang ditemukan
        if (!empty($imageFiles) && !empty($pdfFile)) {
            $type = 'report'; // Gambar dan PDF
        } elseif (!empty($imageFiles)) {
            $type = 'image'; // Hanya gambar
        } elseif (!empty($pdfFile)) {
            $type = 'pdf'; // Hanya PDF
        } else {
            $type = null;
        }
    
        // Simpan data file ke tabel File
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode([
                'images' => $imageFiles,
                'pdf' => $pdfFile
            ]), 
            'type' =>  $type
        ]);
    }
    

    return response()->json([
        "message" => "Submission updated successfully",
    ], 200);
}




}
