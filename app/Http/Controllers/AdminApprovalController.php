<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminApprovalController extends Controller
{

    public function ammount(){
       $approval = Submission::where('finish_status', 'approved')->count();
       $denied = Submission::where('finish_status', 'denied')->count();
       $process = Submission::where('finish_status', 'process')->count();
       $amount = Submission::sum('amount');

      

       return response()->json([
            'data' => ([
                'approval' => $approval,
                'denied' => $denied,
                'process' => $process,
                'amount' => $amount,
            ])
       ]);

    }

    public function dashboard(  Request $request){

        $year = $request->input('year', date('Y')); 
        $month = $request->input('month'); 
    
        $query = Submission::whereYear('submission_date', $year);
        
        if ($month) {
            $query->whereMonth('submission_date', $month); 
        }
        
        $submissions = $query->get(['submission_date', 'type', 'amount']);
    
        $groupedData = [];
    
        foreach ($submissions as $submission) {
            $monthYear = Carbon::parse($submission->submission_date)->translatedFormat('F Y'); 

            if (!isset($groupedData[$monthYear])) {
                $groupedData[$monthYear] = [
                    'Reimburesent' => 0,
                    'Payment Process' => 0,
                ];
            }
    
            $groupedData[$monthYear][$submission->type] += $submission->amount;
        }
    
        $result = [];
        foreach ($groupedData as $monthYear => $data) {
            $result[] = [
                'month' => $monthYear,
                'types' => [
                    'Reimburesent' => $data['Reimburesent'],
                    'Payment Process' => $data['Payment Process'],
                ],
            ];
        }
    
        return response()->json([
            'data' => [
                'chart' => $result,
            ]
        ]);


       

        return response()->json(['data' => $chart, 'message' => 'Users retrieved successfully']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->name;
        $statusFilter = $request->query('status', null);
        $typeFilter = $request->query('type', null);
        $search = $request->query('search', null);
        $dueDateFilter = $request->query('due_date', null);  // Tambahkan filter due_date
        
        $submissions = Submission::whereHas('adminApprovals', function($query) use ($user, $statusFilter) {
            $query->where('user_id', $user->id)
                  ->when($statusFilter, function($query) use ($statusFilter) {
                      return $query->where('status', $statusFilter);
                  })
                  ->where(function($query) {
                      $query->where('status', 'pending')
                            ->orWhere('status', 'approved')
                            ->orWhere('status', 'denied');
                  });
        })
        ->when($typeFilter, function($query) use ($typeFilter) {
            return $query->where('type', $typeFilter);
        })
        ->when($search, function($query) use ($search) {
            return $query->where(function($query) use ($search) {
                $query->where('purpose', 'like', "%$search%") 
                      ->orWhereHas('user', function($subQuery) use ($search) {
                          $subQuery->where('name', 'like', "%$search%");
                      });
            });
        })
        ->when($dueDateFilter, function($query) use ($dueDateFilter) {  // Filter untuk due_date
            return $query->whereDate('due_date', $dueDateFilter);
        })
        ->with(['adminApprovals' => function($query) use ($user, $statusFilter) {
            $query->where('user_id', $user->id)
                  ->when($statusFilter, function($query) use ($statusFilter) {
                      return $query->where('status', $statusFilter);
                  });
        }, 'items', 'files', 'user'])
        ->get();
    
        if ($role === 'Employee') {
            return response()->json([
                'message' => 'Sorry, you are not authorized as an admin.'
            ], 403);
        }



        $submissions->transform(function ($submission) {
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
    
                // Gabungkan image dan pdf URLs dalam satu objek
                $file->all_urls = array_merge($imageUrls, $pdfUrls);
    
                return $file;
            });
    
            return $submission;
        });
    
    
        return response()->json([
            'submissions' => $submissions
        ], 200);
    }
    
    
        


    public function detail($id)
{
    $user = Auth::user();
    $role = $user->role->name;
    $submission = null;

    // Jika user memiliki peran 'GA'
    if ($role === 'GA') {
        $submission = Submission::whereHas('adminApprovals', function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where(function($query) {
                      $query->where('status', 'pending')
                            ->orWhere('status', 'approved')
                            ->orWhere('status', 'denied');
                  });
        })->with('adminApprovals.user.role', 'items', 'files', 'bankAccount.bank')->find($id);
    }
    // Jika user memiliki peran 'Manager'
    elseif ($role === 'Manager') {
        $staffIds = Staff::where('manager_id', $user->id)->pluck('staff_id');
        $submission = Submission::whereIn('user_id', $staffIds)
            ->whereHas('adminApprovals', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where(function($query) {
                          $query->where('status', 'pending')
                                ->orWhere('status', 'approved')
                                ->orWhere('status', 'denied');
                      });
            })->with('adminApprovals.user.role', 'items', 'files', 'bankAccount.bank')->find($id);
    }
    // Jika user memiliki peran 'CEO' atau 'Finance'
    elseif ($role === 'CEO' || $role === 'Finance') {
        $submission = Submission::whereHas('adminApprovals', function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where(function($query) {
                      $query->where('status', 'pending')
                            ->orWhere('status', 'approved')
                            ->orWhere('status', 'denied');
                  });
        })->with('adminApprovals.user.role', 'items', 'files', 'bankAccount.bank')->find($id);
    }
    // Jika user memiliki peran 'Employee'
    elseif ($role === 'Employee') {
        return response()->json([
            'message' => 'Sorry, you are not authorized as an admin.'
        ], 403);
    }

    // Jika submission tidak ditemukan
    if (!$submission) {
        return response()->json([
            'message' => 'Submission not found'
        ], 404);
    }

    // Tambahkan URL lengkap untuk setiap file dalam submission
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
        'submission' => $submission
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
    
        // Update status approval untuk user yang saat ini melakukan persetujuan
        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);
    
        // Atur daftar approvers berdasarkan posisi pengaju
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
    
                // Ambil Manager dari tabel staff untuk user yang mengajukan
                $manager = DB::table('staff')
                    ->where('staff_id', $submission->user->id)
                    ->first();
    
                if ($manager) {
                    $approvers = array_merge(['GA', $manager->manager_id], $approvers);
                }
                break;
        }
    
        // Tentukan approver berikutnya
        $currentApproverIndex = array_search($user->position->name, $approvers);
    
        if ($currentApproverIndex !== false && isset($approvers[$currentApproverIndex + 1])) {
            $nextApproverRole = $approvers[$currentApproverIndex + 1];
    
            // Jika approver berikutnya adalah ID Manager dari tabel `staff`
            if (is_numeric($nextApproverRole)) {
                $nextApproverUser = User::find($nextApproverRole);
            } else {
                // Ambil user berdasarkan role berikutnya
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
   
       // Cari approval untuk user yang sedang melakukan penolakan
       $approval = AdminApproval::where('submission_id', $submissionId)
           ->where('user_id', $user->id)
           ->first();
   
       if (!$approval) {
           return response()->json(['message' => 'Approval tidak ditemukan untuk user ini'], 404);
       }
   
       // Jika status sudah 'denied', kembalikan respons
       if ($approval->status === 'denied') {
           return response()->json(['message' => 'Submission sudah ditolak sebelumnya'], 400);
       }
   
       // Update status approval menjadi 'denied'
       $approval->update([
           'status' => 'denied',
           'notes' => $request->input('notes', null),
           'approved_at' => Carbon::now(),
       ]);
   
       // Jika sudah ada yang menolak, tidak perlu melanjutkan ke approver berikutnya
       // Ubah finish_status dari submission menjadi 'denied'
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
        
        // Ambil semua pengajuan yang masih pending untuk user yang sedang login
        $pendingApprovals = AdminApproval::where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();
        
        if ($pendingApprovals->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pengajuan yang perlu disetujui untuk user ini'], 404);
        }
    
        foreach ($pendingApprovals as $approval) {
            // Update status persetujuan saat ini menjadi 'approved'
            $approval->update([
                'status' => 'approved',
                'notes' => $request->input('notes', null),
                'approved_at' => now(),
            ]);
    
            $submission = $approval->submission;
    
            // Tentukan approver berikutnya berdasarkan role user saat ini
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
    
                    // Ambil Manager dari tabel staff
                    $manager = DB::table('staff')
                        ->where('staff_id', $submission->user->id)
                        ->first();
    
                    if ($manager && isset($manager->manager_id)) {
                        $approvers = array_merge(['GA', $manager->manager_id], $approvers);
                    }
                    break;
            }
    
            // Menambahkan approver berikutnya
            $currentApproverIndex = array_search($user->position->name, $approvers);
         
    
            if ($currentApproverIndex !== false && isset($approvers[$currentApproverIndex + 1])) {
                $nextApproverRole = $approvers[$currentApproverIndex + 1];
    
                // Jika next approver adalah ID Manager
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
    
            // Penanganan spesifik untuk Manager, CEO, dan Finance
            if ($user->role->name == 'Manager') {
                AdminApproval::firstOrCreate([
                    'user_id' => 1, // Misalnya user dengan id 1 adalah CEO
                    'submission_id' => $submission->id,
                    'status' => 'pending',
                ]);
            }
    
            if ($user->role->name == 'CEO') {
                AdminApproval::firstOrCreate([
                    'user_id' => 7, // Misalnya user dengan id 7 adalah Finance
                    'submission_id' => $submission->id,
                    'status' => 'pending',
                ]);
            }


            if ($user->role->name == 'Finance') {
                $submission->update(['finish_status' => 'approved']);
            }
        
        }
    
        return response()->json(['message' => 'Semua persetujuan berhasil diperbarui dan approver berikutnya telah ditambahkan.']);
    }
    
    
    
    


    public function deniedall(Request $request)
    {
        $user = Auth::user();
    
        // Mengambil semua approval yang belum disetujui
        $approvals = AdminApproval::where('user_id', $user->id)
            ->where('status', '!=', 'approved')
            ->get();
    
        // Mengecek apakah tidak ada approval yang perlu ditolak
        if ($approvals->isEmpty()) {
            return response()->json(['message' => 'Tidak ada approval yang perlu ditolak untuk user ini'], 404);
        }
    
        // Update status semua approval menjadi 'denied'
        foreach ($approvals as $approval) {
            $approval->update([
                'status' => 'denied',
                'notes' => $request->input('notes', null),
                'denied_at' => Carbon::now(),
            ]);
        }
    
        // Dapatkan semua submission_id yang terkait dengan approval yang ditolak
        $submissionIds = $approvals->pluck('submission_id')->unique();
    
        // Proses setiap submission untuk memastikan semua approval terkait ditolak
        foreach ($submissionIds as $submissionId) {
            $submission = Submission::find($submissionId);
            
            // Pastikan submission ditemukan
            if ($submission) {
                // Ambil semua approval terkait dengan submission ini
                $relatedApprovals = AdminApproval::where('submission_id', $submissionId)->get();
    
                // Cek apakah semua approval terkait sudah ditolak
                if ($relatedApprovals->every(fn($approval) => $approval->status === 'denied')) {
                    // Update finish_status submission menjadi 'denied' jika semua approval terkait ditolak
                    $submission->update([
                        'finish_status' => 'denied'
                    ]);
                }
            }
        }
    
        return response()->json(["message" => "Semua submission terkait telah ditolak"], 200);
    }
    
    

    
    
}
