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
                $fileArray = json_decode($file->file, true);
                
                // Buat dua array untuk memisahkan image dan pdf
                $imageUrls = [];
                $pdfUrls = [];
                
                if (is_array($fileArray)) {
                    foreach ($fileArray as $fileName) {
                        $fileUrl = url('uploads/submission/' . $fileName);
                
                        // Pisahkan berdasarkan tipe file
                        if ($file->type === 'image') {
                            $imageUrls[] = $fileUrl;
                        } elseif ($file->type === 'pdf') {
                            $pdfUrls[] = $fileUrl;
                        }
                    }
                }
                
                // Menggunakan setAttribute untuk menyimpan data ke properti dinamis jika diperlukan
                $file->image_urls = $imageUrls;
                $file->pdf_urls = $pdfUrls;
            
                return $file;
            });
            
            return $submission;
        });
    
        return response()->json([
            'data' => $submissions
        ]);
    }
    
    


    public function detail($id) {
        $submission = Submission::with('adminApprovals.user.role', 'files', 'items', 'bankAccount.bank')
            ->where('user_id', Auth::user()->id)
            ->find($id);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }
    
        // Tambahkan URL lengkap untuk setiap file
        $submission->files->transform(function ($file) {
            $fileArray = json_decode($file->file, true);
            
            // Buat dua array untuk memisahkan image dan pdf
            $imageUrls = [];
            $pdfUrls = [];
            
            if (is_array($fileArray)) {
                foreach ($fileArray as $fileName) {
                    $fileUrl = url('uploads/submission/' . $fileName);
            
                    // Pisahkan berdasarkan tipe file
                    if ($file->type === 'image') {
                        $imageUrls[] = $fileUrl;
                    } elseif ($file->type === 'pdf') {
                        $pdfUrls[] = $fileUrl;
                    }
                }
            }
            
            // Menggunakan setAttribute untuk menyimpan data ke properti dinamis jika diperlukan
            $file->image_urls = $imageUrls;
            $file->pdf_urls = $pdfUrls;
        
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
        $imageFiles = [];
        $pdfFiles = [];

        foreach ($images as $image) {
            $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/submission'), $imageName);

            // Pisahkan berdasarkan tipe file
            if (in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
                $imageFiles[] = $imageName;
            } elseif ($image->getClientOriginalExtension() === 'pdf') {
                $pdfFiles[] = $imageName;
            }
        }

        // Simpan data file gambar ke tabel File jika ada
        if (!empty($imageFiles)) {
            File::create([
                'submission_id' => $submission->id,
                'file' => json_encode($imageFiles),
                'type' => 'image'
            ]);
        }

        // Simpan data file PDF ke tabel File jika ada
        if (!empty($pdfFiles)) {
            File::create([
                'submission_id' => $submission->id,
                'file' => json_encode($pdfFiles),
                'type' => 'pdf'
            ]);
        }
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
        'bank_account_id' => 'sometimes|integer|exists:bank_accounts,id',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Update submission details
    $submission->fill($request->only(['type', 'purpose', 'due_date', 'bank_account_id']));
    $totalAmount = $submission->amount;

    // Handle submission items (update or add new items)
    // Handle submission items (update or add new items)
// Handle submission items (update or add new items)
// Handle submission items (update or add new items)
if ($request->has('submission_item') && is_array($request->submission_item)) {
    $totalAmount = 0;

    // Ambil semua submission items lama
    $existingItems = SubmissionItem::where('submission_id', $submission->id)->get();

    // Daftar ID item yang akan tetap ada
    $existingItemIds = [];

    // Loop untuk setiap item baru dalam request
    foreach ($request->submission_item as $item) {
        // Cek apakah item sudah ada berdasarkan deskripsi atau identifier lainnya
        $existingItem = $existingItems->firstWhere('description', $item['description']); // Sesuaikan dengan identifier unik

        if ($existingItem) {
            // Update item yang sudah ada
            $existingItem->quantity = $item['quantity'];
            $existingItem->price = $item['price'];
            $existingItem->save();

            // Tambahkan ID item yang diperbarui ke daftar
            $existingItemIds[] = $existingItem->id;
        } else {
            // Tambah item baru jika tidak ditemukan
            $newItem = SubmissionItem::create([
                'submission_id' => $submission->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);

            // Tambahkan ID item yang baru ditambahkan ke daftar
            $existingItemIds[] = $newItem->id;
        }

        // Hitung total amount
        $totalAmount += $item['quantity'] * $item['price'];
    }

    // Hapus item yang tidak ada dalam request (menghapus item yang tidak ada di request)
    // Yang tidak ada di request (id-nya tidak ada di dalam existingItemIds)
    $itemsToDelete = $existingItems->whereNotIn('id', $existingItemIds);
    foreach ($itemsToDelete as $item) {
        $item->delete();
    }

    // Update total amount pada submission
    $submission->amount = $totalAmount;
} else {
    // Handle jika tidak ada atau format submission_item tidak valid
    return response()->json([
        'message' => 'Invalid submission items data'
    ], 422);
}

$submission->save();

if ($request->hasFile('file')) {
    $images = $request->file('file');
    $imageFiles = [];
    $pdfFiles = [];

    foreach ($images as $image) {
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('uploads/submission'), $imageName);

        // Pisahkan berdasarkan tipe file
        if (in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageFiles[] = $imageName;
        } elseif ($image->getClientOriginalExtension() === 'pdf') {
            $pdfFiles[] = $imageName;
        }
    }

    // Simpan data file gambar ke tabel File jika ada
    if (!empty($imageFiles)) {
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode($imageFiles),
            'type' => 'image'
        ]);
    }

    // Simpan data file PDF ke tabel File jika ada
    if (!empty($pdfFiles)) {
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode($pdfFiles),
            'type' => 'pdf'
        ]);
    }
}

    // Handle file uploads (jika diperlukan, sesuaikan dengan logika upload file Anda)

    // Handle admin approval (similar to store)
    $user = Auth::user();
    $positionName = $user->position->name;

    // Check if approval is needed for admin
    if ($positionName !== 'Finance') {
        $approvalData = [
            'submission_id' => $submission->id,
            'status' => 'pending',
        ];

        // Set appropriate admin user for approval based on position
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
            default:
                $approvalData['user_id'] = 6;
                break;
        }

        // Create the admin approval
        AdminApproval::create($approvalData);
    } else {
        // If position is Finance, approve immediately
        AdminApproval::create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    return response()->json(["message" => "Submission updated successfully"], 200);
}




}
