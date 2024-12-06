<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\AdminTransferProof;
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
    public function index(Request $request)
    {
        $user = auth()->user(); 
        $roleId = $user->role_id; 
    
        $query = Submission::where('user_id', $user->id);
    
        if ($request->has('due_date')) {
            $dueDate = $request->query('due_date');
            $query->whereDate('due_date', $dueDate);
        }
    
        if ($request->has('submission_date')) {
            $submissionDate = $request->query('submission_date');
            $query->whereDate('submission_date', $submissionDate);
        }
    
        if ($roleId == 5) {
            $approval = (clone $query)->where('finish_status', 'approved')->count();
            $denied = (clone $query)->where('finish_status', 'denied')->count();
            $process = (clone $query)->where('finish_status', 'process')->count();
            $amount = (clone $query)->where('finish_status', 'process')->sum('amount');
        }
    
        $userId = $user->id;
    
        $query = Submission::with(['adminApprovals', 'files', 'items', 'user'])
            ->where('user_id', $userId);
    
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('purpose', 'LIKE', "%{$search}%")
                ->orWhere('amount', 'LIKE', "%{$search}%");
        });
    
        if ($finish_statuses = $request->input('finish_status')) {
            $query = $query->whereIn('finish_status', $finish_statuses);
        }
    
        if ($type = $request->input('type')) {
            $query = $query->whereIn('type', $type);
        }
    
        if ($request->has('due_date')) {
            $dueDate = $request->input('due_date');
            $query->whereDate('due_date', $dueDate);
        }
    
        if ($request->has('submission_date')) {
            $submissionDate = $request->input('submission_date');
            $query->whereDate('submission_date', $submissionDate);
        }
    
        $perPage = $request->input('per_page', 10); 
        $submissions = $query->paginate($perPage);
    
        $submissions->getCollection()->transform(function ($submission) {
            $submission->files->transform(function ($file) {
                $fileArray = json_decode($file->file, true);
    
                $imageUrls = [];
                $pdfUrls = [];
    
                if (is_array($fileArray)) {
                    foreach ($fileArray as $fileName) {
                        $fileUrl = url('uploads/submission/' . $fileName);
    
                        if ($file->type === 'image') {
                            $imageUrls[] = $fileUrl;
                        } elseif ($file->type === 'pdf') {
                            $pdfUrls[] = $fileUrl;
                        }
                    }
                }
    
                $file->image_urls = $imageUrls;
                $file->pdf_urls = $pdfUrls;
    
                return $file;
            });
    
            return $submission;
        });
    
        return response()->json([
            'data' => [
                'amounts' => [
                    'approval' => $approval ?? 0,
                    'denied' => $denied ?? 0,
                    'process' => $process ?? 0,
                    'amount' => $amount ?? 0,
                ],
                'submissions' => $submissions,
            ],
        ]);
    }
    
    
    

   public function detail($id, Request $request) {
    $submission = Submission::with('adminApprovals.user.role', 'files', 'items', 'user.position')
        ->where('user_id', Auth::user()->id)
        ->find($id);

    if (!$submission) {
        return response()->json(['message' => 'Submission not found'], 404);
    }

    if ($request->has('delete_images')) {
        $deleteImages = $request->input('delete_images');
        
        foreach ($submission->files as $file) {
            $fileArray = json_decode($file->file, true);
            if (is_array($fileArray)) {
                foreach ($fileArray as $fileName) {
                    if (in_array($fileName, $deleteImages)) {
                        $filePath = public_path('uploads/submission/' . $fileName);
                        if (file_exists($filePath)) {
                            unlink($filePath); 
                        }
                    }
                }
            }
        }
    }

    $submission->files->transform(function ($file) {
        $fileArray = json_decode($file->file, true);
        $imageUrls = [];
        $image = [];
        $pdf = [];
        $pdfUrls = [];

        if (is_array($fileArray)) {
            foreach ($fileArray as $fileName) {
                $fileUrl = url('uploads/submission/' . $fileName);
                $files = $fileName;

                if ($file->type === 'image') {
                    $imageUrls[] = $fileUrl;
                    $image[] = $files; 
                } elseif ($file->type === 'pdf') {
                    $pdfUrls[] = $fileUrl;
                    $pdf[] = $files;
                }
            }
        }

        $file->image_urls = $imageUrls;
        $file->pdf_urls = $pdfUrls;
        $file->pdf =  $pdf;
        $file->image = $image;

        return $file;
    });


    $additionalFiles = File::where('submission_id', $submission->id)
        ->get()
        ->map(function ($file) {
            $fileUrl = url('uploads/submission/' . $file->file);
            return [
                'id' => $file->id,
                'type' => $file->type,
                'url' => $fileUrl
            ];
        });


       $proofs = AdminTransferProof::whereIn('admin_approval_id', $submission->adminApprovals->pluck('id'))
       ->get()
       ->flatMap(function ($proof) { 
           $fileArray = json_decode($proof->file, true); 
           
           if (is_array($fileArray)) {
               return array_map(function ($fileName) use ($proof) {
                   return [
                       'id' => $proof->id,
                       'type' => $proof->type,
                       'url' => url('uploads/proofs/' . $fileName),
                   ];
               }, $fileArray);
           }

           return []; 
       });

       return response()->json([
        'data' => [
            'submission' => array_merge($submission->toArray(), [
                'files' => $submission->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'type' => $file->type,
                        'image_urls' => $file->image_urls,
                        'pdf_urls' => $file->pdf_urls,
                        'image' => $file->image,
                        'pdf' => $file->pdf,
                    ];
                }),
                'proofs' => $proofs->groupBy('type')->map(function ($group, $type) {
                    return [
                        'type' => $type,
                        'image_urls' => $type === 'image' ? $group->pluck('url') : [],
                        'pdf_urls' => $type === 'pdf' ? $group->pluck('url') : [],
                        'image' => $type === 'image' ? $group->pluck('url')->map(fn($url) => basename($url)) : [],
                        'pdf' => $type === 'pdf' ? $group->pluck('url')->map(fn($url) => basename($url)) : [],
                    ];
                })->values(),
            ])
        ]
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

    $user = Auth::user();

    $submission = Submission::create([
    'user_id' => $user->id,
    'type' => $request->type,
    'purpose' => $request->purpose,
    'due_date' => $request->due_date,
    'submission_date' => Carbon::now(),
    'bank_name' => $request->bank_name,
    'account_name' => $request->account_name, 
    'account_number' => $request->account_number, 
    'finish_status' => 'process',
    'amount' => 0,
    ]);

    $totalAmount = 0;
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
    $submission->save();

    // Simpan file jika ada
    if ($request->hasFile('file')) {
        $images = $request->file('file');
        $imageFiles = [];
        $pdfFiles = [];

        foreach ($images as $image) {
            $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/submission'), $imageName);
            if (in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
                $imageFiles[] = $imageName;
            } elseif ($image->getClientOriginalExtension() === 'pdf') {
                $pdfFiles[] = $imageName;
            }
        }

        if (!empty($imageFiles)) {
            File::create([
                'submission_id' => $submission->id,
                'file' => json_encode($imageFiles),
                'type' => 'image'
            ]);
        }

        if (!empty($pdfFiles)) {
            File::create([
                'submission_id' => $submission->id,
                'file' => json_encode($pdfFiles),
                'type' => 'pdf'
            ]);
        }
    }

    $positionName = $user->position->name;

    $approvalData = [
        'submission_id' => $submission->id,
        'status' => 'pending',
    ];

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
            AdminApproval::create([
                'user_id' => 7,
                'submission_id' => $submission->id,
                'status' => 'pending',
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
        'bank_name' => 'sometimes|string|max:255',
    'account_name' => 'sometimes|string|max:255', 
    'account_number' => 'sometimes|numeric|digits_between:1,20', 
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }


    $submission->fill($request->only([ 'type', 
    'purpose',
    'due_date',
    'bank_name', 
    'account_name', 
    'account_number',  ]));
    $totalAmount = $submission->amount;

   
if ($request->has('submission_item') && is_array($request->submission_item)) {
    $totalAmount = 0;

   
    $existingItems = SubmissionItem::where('submission_id', $submission->id)->get();
    $existingItemIds = [];
    foreach ($request->submission_item as $item) {
        $existingItem = $existingItems->firstWhere('description', $item['description']);

        if ($existingItem) {
            $existingItem->quantity = $item['quantity'];
            $existingItem->price = $item['price'];
            $existingItem->save();
            $existingItemIds[] = $existingItem->id;
        } else {
            $newItem = SubmissionItem::create([
                'submission_id' => $submission->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);

           
            $existingItemIds[] = $newItem->id;
        }


        $totalAmount += $item['quantity'] * $item['price'];
    }

    $submission->save();
    $submission->amount = $totalAmount;
} else {
    return response()->json([
        'message' => 'Invalid submission items data'
    ], 422);
}

$submission->save();



if ($request->has('delete_files') && is_array($request->delete_files)) {
    foreach ($request->delete_files as $filename) {
        $fileRecord = File::where('submission_id', $submission->id)
            ->whereJsonContains('file', $filename)
            ->first();

        if ($fileRecord) {
            $files = json_decode($fileRecord->file, true);
            if (($key = array_search($filename, $files)) !== false) {
                unset($files[$key]);
                if (file_exists(public_path("uploads/submission/$filename"))) {
                    unlink(public_path("uploads/submission/$filename"));
                }
            }

            $fileRecord->file = json_encode(array_values($files));
            $fileRecord->save();
        }
    }
}

if ($request->hasFile('file')) {
    $images = $request->file('file');
    $imageFiles = [];
    $pdfFiles = [];

    foreach ($images as $image) {
        $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('uploads/submission'), $imageName);

        if (in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageFiles[] = $imageName;
        } elseif ($image->getClientOriginalExtension() === 'pdf') {
            $pdfFiles[] = $imageName;
        }
    }

    if (!empty($imageFiles)) {
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode($imageFiles),
            'type' => 'image'
        ]);
    }

    if (!empty($pdfFiles)) {
        File::create([
            'submission_id' => $submission->id,
            'file' => json_encode($pdfFiles),
            'type' => 'pdf'
        ]);
    }
}


    $user = Auth::user();
    $positionName = $user->position->name;

    if ($positionName !== 'Finance') {
        $approvalData = [
            'submission_id' => $submission->id,
            'status' => 'pending',
        ];

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

        AdminApproval::create($approvalData);
    } else {
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
