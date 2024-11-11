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
            })->with('adminApprovals', 'items', 'files', 'bankAccount.bank')->find($id);
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
                })->with('adminApprovals', 'items', 'files', 'bankAccount.bank')->find($id);
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
            })->with('adminApprovals', 'items', 'files', 'bankAccount.bank')->find($id);
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
    
            if (is_array($fileArray)) {
                $file->file_urls = collect($fileArray)->map(function ($fileName) {
                    return url('uploads/submission/' . $fileName);
                })->toArray();
            } else {
                $file->file_url = url('uploads/submission/' . $file->file);
            }
    
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
    
        // Tambahan aturan khusus untuk memastikan semua approvers dilibatkan secara berurutan
        if ($user->role->name == 'Manager' && $submission->user->position->name == 'GA') {
            $ceoUser = User::whereHas('role', function ($query) {
                $query->where('name', 'CEO');
            })->first();
    
            if ($ceoUser) {
                AdminApproval::firstOrCreate([
                    'user_id' => $ceoUser->id,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
            }
        }
    
        if ($user->role->name == 'CEO' && ($submission->user->position->name == 'GA' || $submission->user->position->name == 'Manager')) {
            $financeUser = User::whereHas('role', function ($query) {
                $query->where('name', 'Finance');
            })->first();
    
            if ($financeUser) {
                AdminApproval::firstOrCreate([
                    'user_id' => $financeUser->id,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
            }
        }
    
        // Jika user adalah Finance, ubah status submission menjadi 'approved'
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

    



    
    public function approveall(Request $request, $submissionId)
    {
        $user = Auth::user();
        $submission = Submission::find($submissionId);
    
        if (!$submission) {
            return response()->json(['message' => 'Submission tidak ditemukan'], 404);
        }
    
        // Ambil semua approval yang terkait dengan submission ini
        $approvals = AdminApproval::where('submission_id', $submissionId)->get();
    
        // Cek apakah sudah ada approval dari user ini
        $approval = $approvals->firstWhere('user_id', $user->id);
        
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
    
        // Proses update semua approval untuk submission ini
        foreach ($approvals as $approval) {
            // Jika status approval belum approved, update statusnya
            if ($approval->status !== 'approved') {
                $approval->update([
                    'status' => 'approved',
                    'approved_at' => Carbon::now(),
                    'notes' => $request->input('notes', null), // Menggunakan catatan yang sama jika perlu
                ]);
            }
        }
    
        // Tentukan approver berikutnya dan buat approval untuk mereka
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
    
            // Jika approver berikutnya adalah ID Manager dari tabel staff
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
    
        // Proses untuk user yang berperan sebagai Manager, CEO, atau Finance
        if ($user->role->name == 'Manager') {
            $ceoUser = User::whereHas('role', function ($query) {
                $query->where('name', 'CEO');
            })->first();
    
            if ($ceoUser) {
                AdminApproval::firstOrCreate([
                    'user_id' => $ceoUser->id,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
            }
        }
    
        if ($user->role->name == 'CEO') {
            $financeUser = User::whereHas('role', function ($query) {
                $query->where('name', 'Finance');
            })->first();
    
            if ($financeUser) {
                AdminApproval::firstOrCreate([
                    'user_id' => $financeUser->id,
                    'submission_id' => $submissionId,
                    'status' => 'pending'
                ]);
            }
        }
    
        // Jika user adalah Finance, ubah status submission menjadi 'approved'
        if ($user->role->name == 'Finance') {
            $submission->update(['finish_status' => 'approved']);
        }
    
        return response()->json(["message" => "Approval berhasil"], 200);
    }


    public function deniedll(Request $request, $submissionId)
{
    $user = Auth::user();
    $submission = Submission::find($submissionId);

    if (!$submission) {
        return response()->json(['message' => 'Submission tidak ditemukan'], 404);
    }

    // Ambil semua approval yang terkait dengan submission ini
    $approvals = AdminApproval::where('submission_id', $submissionId)->get();

    // Cek apakah sudah ada approval atau denial dari user ini
    $approval = $approvals->firstWhere('user_id', $user->id);

    if (!$approval) {
        return response()->json(['message' => 'Approval tidak ditemukan untuk user ini'], 404);
    }

    if ($approval->status === 'denied') {
        return response()->json(['message' => 'Submission sudah ditolak sebelumnya'], 400);
    }

    // Update status approval untuk user yang saat ini melakukan penolakan
    $approval->update([
        'status' => 'denied',
        'notes' => $request->input('notes', null),
        'denied_at' => Carbon::now(),
    ]);

    // Jika ada approval yang ditolak, ubah finish_status submission menjadi 'denied' dan hentikan proses
    $submission->update(['finish_status' => 'denied']);

    // Tidak perlu melanjutkan ke approver berikutnya, cukup update approval yang ditolak
    return response()->json(["message" => "Submission telah ditolak"], 200);
}

    
    
}
