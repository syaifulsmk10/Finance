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
    public function index(){
        $submission = Submission::with('adminApprovals', 'files', 'items')->where('user_id', Auth::user()->id)->get();

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
    
}
