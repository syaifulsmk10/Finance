<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\File;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\SubmissionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubmissionController extends Controller
{
    public function index(Request $request) {
        // Ambil user yang sedang login
        $userId = Auth::user()->id;
    
        // Ambil query submission dengan relasi
        $query = Submission::with('adminApprovals', 'files', 'items')
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
    
        return response()->json([
            'data' => $submissions
        ]);
    }
    


    public function detail($id){
        $submission = Submission::with('adminApprovals', 'files', 'items', 'bankAccount.bank')->where('user_id', Auth::user()->id)->find($id);

        return response()->json([
            'data' => $submission
        ]);
    }

    
    public function store(Request $request)

    {

        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'purpose' => 'required|string',
            'due_date' => 'required|date',
            'submission_item' => 'required|array',
            'submission_item.*.description' => 'required|string',
            'submission_item.*.quantity' => 'required|integer|min:1',
            'submission_item.*.price' => 'required|numeric|min:0',
        ]);
        

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Hitung total amount dari submission item
        $user = Auth::user();

        // Membuat submission
        $submission = Submission::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'purpose' => $request->purpose,
            'due_date' => $request->due_date,
            'submission_date' => Carbon::now(),
            'bank_account_id' => $request->bank_account_id,
            'finish_status' => 'process', // Status awal
            'amount' => 0 // Akan diupdate nanti
        ]);

        $totalAmount = 0;

        // Menyimpan submission items
        foreach ($request->submission_item as $item) {
            SubmissionItem::create([
                'submission_id' => $submission->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);

            $totalAmount += $item['quantity'] * $item['price'];
        }

        // Update total amount di submission
        $submission->amount = $totalAmount;
        $submission->save();

        if ($request->hasFile('file')) {
            $images = $request->file('file');
            $imagefiles = [];
    
            foreach ($images as $image) {
                $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/submission'), $imageName);
                $imagefiles[] = $imageName;
            }
    
            File::create([
                'submission_id' => $submission->id,
                'file' => json_encode($imagefiles), 
            ]);



            AdminApproval::create([
                'user_id' => 6,
                'submission_id' => $submission->id,
                'status' => 'pending'
            ]);
    
            return response()->json([
                "message" => "Success Add submission",
            ], 200);
        } else {
            return response()->json([
                "message" => "No file found",
            ], 200);
        }
    }
    



public function update(Request $request, $id)
{
    $submission = Submission::findOrFail($id);

    // Cek apakah approval admin pertama (GA) masih pending
    $adminApprovalFirst = AdminApproval::where('submission_id', $submission->id)
        ->whereHas('user.role', function ($query) {
            $query->where('name', 'GA'); // Mengacu pada admin GA
        })
        ->first();

    // Jika admin pertama sudah approve, maka update tidak bisa dilakukan
    if ($adminApprovalFirst && $adminApprovalFirst->status !== 'pending') {
        return response()->json([
            "message" => "Submission cannot be updated because it has already been approved by the first admin (GA)."
        ], 403); // Forbidden jika sudah diapprove admin pertama
    }

    // Validator untuk input request
    $validator = Validator::make($request->all(), [
        'type' => 'sometimes|string',
        'purpose' => 'sometimes|string',
        'due_date' => 'sometimes|date',
        'submission_item' => 'sometimes|array',
        'submission_item.*.description' => 'sometimes|string',
        'submission_item.*.quantity' => 'sometimes|integer|min:1',
        'submission_item.*.price' => 'sometimes|numeric|min:0',
        'file' => 'sometimes|array',
        'file.*' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:2048'
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Update fields jika ada di request
    if ($request->has('type')) {
        $submission->type = $request->type;
    }
    if ($request->has('purpose')) {
        $submission->purpose = $request->purpose;
    }
    if ($request->has('due_date')) {
        $submission->due_date = $request->due_date;
    }

    $totalAmount = 0;

    // Hapus submission_item yang ada lalu tambahkan yang baru jika ada
    if ($request->has('submission_item')) {
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
    }

    // Update total amount jika ada submission item baru
    if ($request->has('submission_item')) {
        $submission->amount = $totalAmount;
    }

    $submission->save();

    // Handle file update jika ada file yang di-upload
    if ($request->hasFile('file')) {
        $images = $request->file('file');
        $imagefiles = [];

        foreach ($images as $image) {
            $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/submission'), $imageName);
            $imagefiles[] = $imageName;
        }

        // Update atau buat file baru di database
        File::updateOrCreate(
            ['submission_id' => $submission->id],
            ['file' => json_encode($imagefiles)]
        );
    }

    return response()->json([
        "message" => "Submission updated successfully",
    ], 200);
}


}
