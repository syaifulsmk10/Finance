<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\AdminTransferProof;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Log;  
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
   

class AdminApprovalController extends Controller
{

    // public function amount()
    // {
    //     $user = auth()->user(); 
    //     $roleId = $user->role_id; 
    
    //     if ($roleId != 5) {
    //         $approval = Submission::where('finish_status', 'approved')->count();
    //         $denied = Submission::where('finish_status', 'denied')->count();
    //         $process = Submission::where('finish_status', 'process')->count();
    //         $amount = Submission::where('finish_status', 'process')->sum('amount');
    //     } else {
    //         $approval = Submission::where('user_id', $user->id)
    //                               ->where('finish_status', 'approved')
    //                               ->count();
    //         $denied = Submission::where('user_id', $user->id)
    //                             ->where('finish_status', 'denied')
    //                             ->count();
    //         $process = Submission::where('user_id', $user->id)
    //                              ->where('finish_status', 'process')
    //                              ->count();
    //         $amount = Submission::where('user_id', $user->id)
    //                             ->where('finish_status', 'process')
    //                             ->sum('amount');
    //     }
    
    //     return response()->json([
    //         'data' => [
    //             'approval' => $approval,
    //             'denied' => $denied,
    //             'process' => $process,
    //             'amount' => $amount,
    //         ]
    //     ]);
    // }
    


   public function dashboard(Request $request)
{
    $user = auth()->user(); 
    $roleId = $user->role_id; 

    $approval = 0;
    $denied = 0;
    $process = 0;
    $amount = 0;

    $year = $request->input('year', date('Y')); 
    $month = $request->input('month'); 
    $minAmount = $request->input('min_amount', 0);
    $maxAmount = $request->input('max_amount', PHP_INT_MAX);

    $query = Submission::whereYear('submission_date', $year)
                       ->whereBetween('amount', [$minAmount, $maxAmount]);

    if ($month) {
        $query->whereMonth('submission_date', $month); 
    }

    if ($roleId != 5) {
        $approval = Submission::where('finish_status', 'approved')
                               ->whereYear('submission_date', $year)
                               ->whereBetween('amount', [$minAmount, $maxAmount])
                               ->when($month, function ($q) use ($month) {
                                   return $q->whereMonth('submission_date', $month);
                               })
                               ->count();

        $denied = Submission::where('finish_status', 'denied')
                             ->whereYear('submission_date', $year)
                             ->whereBetween('amount', [$minAmount, $maxAmount])
                             ->when($month, function ($q) use ($month) {
                                 return $q->whereMonth('submission_date', $month);
                             })
                             ->count();

        $process = Submission::where('finish_status', 'process')
                              ->whereYear('submission_date', $year)
                              ->whereBetween('amount', [$minAmount, $maxAmount])
                              ->when($month, function ($q) use ($month) {
                                  return $q->whereMonth('submission_date', $month);
                              })
                              ->count();

        $amount = Submission::where('finish_status', 'process')
                             ->whereYear('submission_date', $year)
                             ->whereBetween('amount', [$minAmount, $maxAmount])
                             ->when($month, function ($q) use ($month) {
                                 return $q->whereMonth('submission_date', $month);
                             })
                             ->sum('amount');
    }

    $submissions = $query->get(['submission_date', 'type', 'amount']);

    $groupedData = [];

    foreach ($submissions as $submission) {
        $monthYear = Carbon::parse($submission->submission_date)->translatedFormat('F Y'); 

        if (!isset($groupedData[$monthYear])) {
            $groupedData[$monthYear] = [
                'Reimbursement' => 0,
                'Payment Request' => 0,
            ];
        }

        $groupedData[$monthYear][$submission->type] += $submission->amount;
    }

    $result = [];
    foreach ($groupedData as $monthYear => $data) {
        $result[] = [
            'month' => $monthYear,
            'types' => [
                'Reimbursement' => $data['Reimbursement'],
                'Payment Request' => $data['Payment Request'],
            ],
        ];
    }

    return response()->json([
        'data' => [
            'amountSummary' => [
                'approval' => $approval,
                'denied' => $denied,
                'process' => $process,
                'amount' => $amount,
            ],
            'dashboardChart' => $result,
        ]
    ]);
}


    public function index(Request $request)
{
    $user = auth()->user(); 
    $roleId = $user->role_id; 
    $role = $user->role->name;
    $statusFilter = $request->query('status', null);
    $typeFilter = $request->query('type', null);
    $search = $request->query('search', null);
    $dueDateFilter = $request->query('due_date', null);
    $submissionDateFilter = $request->query('submission_date', null); 
    $perPage = $request->query('per_page', 10);

    $approval = $denied = $process = $amount = 0;

    if ($roleId != 5) {
        $query = Submission::query();
        $query->when($dueDateFilter, function ($query) use ($dueDateFilter) {
            return $query->whereDate('due_date', $dueDateFilter);
        });

        $query->when($submissionDateFilter, function ($query) use ($submissionDateFilter) {
            return $query->whereDate('submission_date', $submissionDateFilter);
        });

        $approval = (clone $query)->where('finish_status', 'approved')->count();
        $denied = (clone $query)->where('finish_status', 'denied')->count();
        $process = (clone $query)->where('finish_status', 'process')->count();
        $amount = (clone $query)->where('finish_status', 'process')->sum('amount');
    }

    $submissions = Submission::whereHas('adminApprovals', function ($query) use ($user, $statusFilter) {
            $query->where('user_id', $user->id)
                  ->when($statusFilter, function ($query) use ($statusFilter) {
                      return $query->whereIn('status', (array)$statusFilter);
                  })
                  ->where(function ($query) {
                      $query->where('status', 'pending')
                            ->orWhere('status', 'approved')
                            ->orWhere('status', 'denied');
                  });
        })
        ->when($typeFilter, function ($query) use ($typeFilter) {
            return $query->whereIn('type', $typeFilter);
        })
        ->when($search, function ($query) use ($search) {
            return $query->where(function ($query) use ($search) {
                $query->where('purpose', 'like', "%$search%")
                      ->orWhereHas('user', function ($subQuery) use ($search) {
                          $subQuery->where('name', 'like', "%$search%");
                      });
            });
        })
        ->when($dueDateFilter, function ($query) use ($dueDateFilter) {
            return $query->whereDate('due_date', $dueDateFilter);
        })
        ->when($submissionDateFilter, function ($query) use ($submissionDateFilter) {
            return $query->whereDate('submission_date', $submissionDateFilter);
        })
        ->with(['adminApprovals' => function ($query) use ($user, $statusFilter) {
            $query->where('user_id', $user->id)
                  ->when($statusFilter, function ($query) use ($statusFilter) {
                      return $query->whereIn('status', (array)$statusFilter);
                  });
        }, 'items', 'files', 'user'])
        ->paginate($perPage);

    if ($role === 'Employee') {
        return response()->json([
            'message' => 'Sorry, you are not authorized as an admin.'
        ], 403);
    }

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
            $file->all_urls = array_merge($imageUrls, $pdfUrls);

            return $file;
        });

        return $submission;
    });

    return response()->json([
        'data' => [
            'approval' => $approval,
            'denied' => $denied,
            'process' => $process,
            'amount' => $amount,
        ],
        'submissions' => $submissions
    ], 200);
}

    
    
    
    
        


public function detail($id)
{
    $user = Auth::user();
    $role = $user->role->name;
    $submission = null;

    if ($role === 'GA') {
        $submission = Submission::whereHas('adminApprovals', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere('status', 'approved')
                        ->orWhere('status', 'denied');
                });
        })->with('adminApprovals.user.role', 'items', 'files', 'user.position')->find($id);
    } elseif ($role === 'Manager') {
        $userPosition = $user->position->name;

        if ($userPosition === 'staff') {
            $staffIds = Staff::where('manager_id', $user->id)->pluck('staff_id');
            $submission = Submission::whereIn('user_id', $staffIds)
                ->whereHas('adminApprovals', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where(function ($query) {
                            $query->where('status', 'pending')
                                ->orWhere('status', 'approved')
                                ->orWhere('status', 'denied');
                        });
                })->with('adminApprovals.user.role', 'items', 'files', 'user.position')->find($id);
        } else {
            $submission = Submission::whereHas('adminApprovals', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where(function ($query) {
                        $query->where('status', 'pending')
                            ->orWhere('status', 'approved')
                            ->orWhere('status', 'denied');
                    });
            })->with('adminApprovals.user.role', 'items', 'files', 'user.position')->find($id);
        }
    } elseif (in_array($role, ['CEO', 'Finance'])) {
        $submission = Submission::whereHas('adminApprovals', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere('status', 'approved')
                        ->orWhere('status', 'denied');
                });
        })->with('adminApprovals.user.role', 'items', 'files', 'user.position')->find($id);
    } elseif ($role === 'Employee') {
        return response()->json([
            'message' => 'Sorry, you are not authorized as an admin.',
        ], 403);
    }

    if (!$submission) {
        return response()->json([
            'message' => 'Submission not found',
        ], 404);
    }

    // Format files
    $submission->files->transform(function ($file) {
        $fileArray = json_decode($file->file, true);

        $imageUrls = [];
        $pdfUrls = [];
        $image = [];
        $pdf = [];

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
        $file->pdf = $pdf;
        $file->image = $image;

        return $file;
    });

    // Tambahkan proofs ke submission
    $proofs = AdminTransferProof::whereIn('admin_approval_id', $submission->adminApprovals->pluck('id'))->get();

    $submission->proofs = $proofs->transform(function ($proof) {
        $fileArray = json_decode($proof->file, true);

        $imageUrls = [];
        $pdfUrls = [];
        $image = [];
        $pdf = [];

        if (is_array($fileArray)) {
            foreach ($fileArray as $fileName) {
                $fileUrl = url('uploads/submission/' . $fileName);
                $files = $fileName;

                if ($proof->type === 'image') {
                    $imageUrls[] = $fileUrl;
                    $image[] = $files;
                } elseif ($proof->type === 'pdf') {
                    $pdfUrls[] = $fileUrl;
                    $pdf[] = $files;
                }
            }
        }

        return [
            'id' => $proof->id,
            'type' => $proof->type,
            'image_urls' => $imageUrls,
            'pdf_urls' => $pdfUrls,
            'image' => $image,
            'pdf' => $pdf,
        ];
    });

    return response()->json([
        'submission' => $submission,
    ], 200);
}



    
        
        


    public function approve(Request $request, $submissionId)
    {
        $user = Auth::user();
        $submission = Submission::find($submissionId);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission tidak ditemukan'], 404);
        }
    
        $approval = AdminApproval::where('submission_id', $submissionId)
            ->where('user_id', $user->id)
            ->first();
    
        if (!$approval) {
            return response()->json(['message' => 'Approval tidak ditemukan untuk user ini'], 404);
        }
    
        if ($approval->status === 'approved') {
            return response()->json(['message' => 'Submission sudah disetujui sebelumnya'], 400);
        }
    
        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);

        $approvers = [];
        switch ($submission->user->position->name) {
            case 'GA':
                $approvers = ['Manager', 'CEO', 'Finance'];
                break;
            case 'Manager':
                $approvers = ['CEO', 'Finance'];
                break;
            case 'CEO':
                $approvers = ['Finance'];
                break;
            case 'Finance':
                $approvers = ['Finance'];
                break;
            default:
                $approvers = ['GA', 'CEO', 'Finance'];
    
                $manager = DB::table('staff')
                    ->where('staff_id', $submission->user->id)
                    ->first();
    
                if ($manager) {
                    $approvers = array_merge(['GA', $manager->manager_id], $approvers);
                }
                break;
        }
    
        $currentApproverIndex = array_search($user->position->name, $approvers);
    
        if ($currentApproverIndex !== false && isset($approvers[$currentApproverIndex + 1])) {
            $nextApproverRole = $approvers[$currentApproverIndex + 1];
    
            if (is_numeric($nextApproverRole)) {
                $nextApproverUser = User::find($nextApproverRole);
            } else {

                $nextApproverUser = User::whereHas('role', function ($query) use ($nextApproverRole) {
                    $query->where('name', $nextApproverRole);
                })->first();
            }
    
            if ($nextApproverUser) {
                AdminApproval::firstOrCreate([
                    'user_id' => $nextApproverUser->id,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
            }
        }
    
        
        if ($user->role->name == 'Manager') {
           
                AdminApproval::firstOrCreate([
                    'user_id' => 1,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
         
        }
    
        if ($user->role->name == 'CEO') {
          
                AdminApproval::firstOrCreate([
                    'user_id' => 7,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
        }
        
        

        if ($user->role->name == 'Finance') {
         
            // if ($request->hasFile('file')) {
            //     $images = $request->file('file');
            //     $imageFiles = [];
            //     $pdfFiles = [];
            
            //     foreach ($images as $image) {
            //         $imageName = 'VA' . Str::random(40) . '.' . $image->getClientOriginalExtension();
            //         $image->move(public_path('uploads/submission'), $imageName);
            
            //         // Pisahkan berdasarkan tipe file
            //         if (in_array($image->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
            //             $imageFiles[] = $imageName;
            //         } elseif ($image->getClientOriginalExtension() === 'pdf') {
            //             $pdfFiles[] = $imageName;
            //         }
            //     }
            
            //     // Ambil ID approval saat ini
            //     $adminApproval = AdminApproval::where('submission_id', $submission->id)
            //         ->where('user_id', $user->id)
            //         ->first();
            
            //     if (!$adminApproval) {
            //         return response()->json(['message' => 'Approval tidak ditemukan'], 404);
            //     }

               
            
            //     // Simpan data file gambar ke tabel File jika ada
            //     if (!empty($imageFiles)) {
            //         AdminTransferProof::create([
            //             'admin_approval_id' => $adminApproval->id,
            //             'file' => json_encode($imageFiles),
            //             'type' => 'image'
            //         ]);
            //     }
            
            //     // Simpan data file PDF ke tabel File jika ada
            //     if (!empty($pdfFiles)) {
            //         AdminTransferProof::create([
            //             'admin_approval_id' => $adminApproval->id,
            //             'file' => json_encode($pdfFiles),
            //             'type' => 'pdf'
            //         ]);
            //     }
            // }
            
            $submission->update(['finish_status' => 'approved']);
        }
    
        return response()->json(["message" => "Approval berhasil"], 200);
    }
    
    

   public function denied(Request $request, $submissionId)
   {
       $user = Auth::user();
       $submission = Submission::find($submissionId);
      
       if (!$submission) {
           return response()->json(['message' => 'Submission tidak ditemukan'], 404);
       }
   
       $approval = AdminApproval::where('submission_id', $submissionId)
           ->where('user_id', $user->id)
           ->first();
   
       if (!$approval) {
           return response()->json(['message' => 'Approval tidak ditemukan untuk user ini'], 404);
       }
   
      
       if ($approval->status === 'denied') {
           return response()->json(['message' => 'Submission sudah ditolak sebelumnya'], 400);
       }
   
       $approval->update([
           'status' => 'denied',
           'notes' => $request->input('notes', null),
           'approved_at' => Carbon::now(),
       ]);
   
       $submission->update(['finish_status' => 'denied']);
   
       return response()->json(["message" => "Submission ditolak"], 200);
   }
   
    
    private function getManagerForStaff($staffId)
    {

        $staff = Staff::where('staff_id', $staffId)->first();
        return $staff ? $staff->manager_id : null;
    }

    

    public function approveall(Request $request)
    {
        $user = Auth::user();
        
        // Get the selected IDs from the request
        $selectedIds = $request->input('selected_ids', []);
        
        
        if (empty($selectedIds)) {
            return response()->json(['message' => 'Tidak ada pengajuan yang dipilih untuk disetujui'], 400);
        }
    
        // Get only the pending approvals that match the selected IDs
        $pendingApprovals = AdminApproval::whereIn('submission_id', $selectedIds)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();
        
        if ($pendingApprovals->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pengajuan yang perlu disetujui untuk user ini'], 404);
        }
    
        foreach ($pendingApprovals as $approval) {
            $approval->update([
                'status' => 'approved',
                'notes' => $request->input('notes', null),
                'approved_at' => now(),
            ]);
    
            $submission = $approval->submission;
    
            $approvers = [];
            switch ($submission->user->position->name) {
                case 'GA':
                    $approvers = ['Manager', 'CEO', 'Finance'];
                    break;
                case 'Manager':
                    $approvers = ['CEO', 'Finance'];
                    break;
                case 'CEO':
                    $approvers = ['Finance'];
                    break;
                case 'Finance':
                    $submission->update(['finish_status' => 'approved']);
                    break;
                default:
                    $approvers = ['GA', 'CEO', 'Finance'];
    
                    $manager = DB::table('staff')
                        ->where('staff_id', $submission->user->id)
                        ->first();
    
                    if ($manager && isset($manager->manager_id)) {
                        $approvers = array_merge(['GA', $manager->manager_id], $approvers);
                    }
                    break;
            }
    
            $currentApproverIndex = array_search($user->position->name, $approvers);
    
            if ($currentApproverIndex !== false && isset($approvers[$currentApproverIndex + 1])) {
                $nextApproverRole = $approvers[$currentApproverIndex + 1];
    
                if (is_numeric($nextApproverRole)) {
                    $nextApproverUser = User::find($nextApproverRole);
                } else {
                    $nextApproverUser = User::whereHas('role', function ($query) use ($nextApproverRole) {
                        $query->where('name', $nextApproverRole);
                    })->first();
                }
    
                if ($nextApproverUser) {
                    AdminApproval::firstOrCreate([
                        'user_id' => $nextApproverUser->id,
                        'submission_id' => $submission->id,
                        'status' => 'pending'
                    ]);
                }
            }
    
            if ($user->role->name == 'Manager') {
                AdminApproval::firstOrCreate([
                    'user_id' => 1, 
                    'submission_id' => $submission->id,
                    'status' => 'pending',
                ]);
            }
    
            if ($user->role->name == 'CEO') {
                AdminApproval::firstOrCreate([
                    'user_id' => 7,
                    'submission_id' => $submission->id,
                    'status' => 'pending',
                ]);
            }
    
            if ($user->role->name == 'Finance') {
                $submission->update(['finish_status' => 'approved']);
            }
        }
    
        return response()->json(['message' => 'Semua persetujuan yang dipilih berhasil diperbarui dan approver berikutnya telah ditambahkan.']);
    }
    
    
    
    
    


    public function deniedall(Request $request)
    {
        $user = Auth::user();
        
        // Get the selected IDs from the request
        $selectedIds = $request->input('selected_ids', []);
        
        
        if (empty($selectedIds)) {
            return response()->json(['message' => 'Tidak ada pengajuan yang dipilih untuk disetujui'], 400);
        }
    
        // Get only the pending approvals that match the selected IDs
        $approvals = AdminApproval::whereIn('submission_id', $selectedIds)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();

          
        
        if ($approvals->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pengajuan yang perlu disetujui untuk user ini'], 404);
        }

           
    
        if ($approvals->isEmpty()) {
            return response()->json(['message' => 'Tidak ada approval yang perlu ditolak untuk user ini'], 404);
        }
    
        foreach ($approvals as $approval) {
            // Update status menjadi 'denied'
            $approval->update([
                'status' => 'denied',
                'notes' => $request->input('notes', null),
                'denied_at' => Carbon::now(),
            ]);
        }
    
        // Ambil ID submission yang terkait
        $submissionIds = $approvals->pluck('submission_id')->unique();
    
        foreach ($submissionIds as $submissionId) {
            $submission = Submission::find($submissionId);
    
            if ($submission) {
                // Cek apakah ada approvals terkait yang sudah ditolak
                $relatedApprovals = AdminApproval::where('submission_id', $submissionId)->get();
    
                // Jika semua approval terkait ditolak, perbarui finish_status
                if ($relatedApprovals->every(function($approval) {
                    return $approval->status === 'denied';
                })) {
                    $submission->update([
                        'finish_status' => 'denied'
                    ]);
                }
            }
        }
    
        return response()->json(["message" => "Semua submission terkait telah ditolak dan finish_status diperbarui jika perlu"], 200);
    }
    
     

    
    
    public function checkDocument(Request $request, $submissionId)
    {   
        $user = Auth::user();
        $submission = Submission::find($submissionId);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission tidak ditemukan'], 404);
        }
        $adminApproval = AdminApproval::where('submission_id', $submission->id)
            ->where('user_id', $user->id)
            ->first();
        $adminApproval->is_checked = true;
        $adminApproval->checked_at = now();
        $adminApproval->save();
    
        return response()->json(['message' => 'Dokumen berhasil diceklis.']);
    }



    public function proof(Request $request, $submissionId)
    {
        $user = Auth::user();
        $submission = Submission::find($submissionId);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission tidak ditemukan'], 404);
        }
    
        $adminApproval = AdminApproval::where('submission_id', $submission->id)
            ->where('user_id', $user->id)
            ->first();
    
        if (!$adminApproval) {
            return response()->json(['message' => 'Approval tidak ditemukan'], 404);
        }
    
        if ($adminApproval->status !== 'approved') {
            return response()->json(['message' => 'Submission belum di-approve'], 403);
        }
    
        if ($request->hasFile('file')) {
            $files = $request->file('file');
            $imageFiles = [];
            $pdfFiles = [];
    
            foreach ($files as $file) {
                $fileName = 'VA' . Str::random(40) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/submission'), $fileName);
    
                // Pisahkan berdasarkan tipe file
                if (in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imageFiles[] = $fileName;
                } elseif ($file->getClientOriginalExtension() === 'pdf') {
                    $pdfFiles[] = $fileName;
                }
            }

           
    
            // Simpan data file gambar ke tabel admin_transfer_proofs jika ada
            if (!empty($imageFiles)) {
                AdminTransferProof::create([
                    'admin_approval_id' => $adminApproval->id,
                    'file' => json_encode($imageFiles),
                    'type' => 'image'
                ]);
            }
    
            // Simpan data file PDF ke tabel admin_transfer_proofs jika ada
            if (!empty($pdfFiles)) {
                AdminTransferProof::create([
                    'admin_approval_id' => $adminApproval->id,
                    'file' => json_encode($pdfFiles),
                    'type' => 'pdf'
                ]);
            }
    
            return response()->json(['message' => 'Bukti berhasil diunggah'], 200);
        }
    
        return response()->json(['message' => 'Tidak ada file yang diunggah'], 400);
    }
    
    
}
