<?php

namespace App\Http\Controllers;

use App\Models\AdminApproval;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
        $search = $request->query('search', null); // Mendapatkan filter pencarian dari query parameter
    
        // Mendapatkan submissions berdasarkan role
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
                $query->where('purpose', 'like', "%$search%") // Filter berdasarkan purpose
                      ->orWhereHas('user', function($subQuery) use ($search) {
                          $subQuery->where('name', 'like', "%$search%"); // Filter berdasarkan nama user
                      });
            });
        })
        ->with(['adminApprovals' => function($query) use ($user, $statusFilter) {
            $query->where('user_id', $user->id)
                  ->when($statusFilter, function($query) use ($statusFilter) {
                      return $query->where('status', $statusFilter);
                  });
        }, 'items', 'files'])
        ->get();
    
        // Jika user memiliki peran 'Employee' (tidak berhak akses)
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
                })->with('adminApprovals', 'items', 'files')->find($id);
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
                    })->with('adminApprovals', 'items', 'files')->find($id);
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
                })->with('adminApprovals', 'items', 'files')->find($id);
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
        
            return response()->json([
                'submission' => $submission
            ], 200);
        }
        
        



public function approve(Request $request, $submissionId)
{
    $user = Auth::user();

    // Cek apakah submissionId ada
    $submission = Submission::find($submissionId);
    if (!$submission) {
        return response()->json(['message' => 'Submission tidak ditemukan'], 404);
    }

    $approval = AdminApproval::where('submission_id', $submissionId)
        ->where('user_id', $user->id)
        ->first();

    // Cek jika approval tidak ditemukan untuk user ini
    if (!$approval) {
        return response()->json(['message' => 'Approval tidak ditemukan untuk user ini'], 404);
    }

    // Cek jika approval sudah disetujui
    if ($approval->status === 'approved') {
        return response()->json(['message' => 'Submission sudah disetujui sebelumnya'], 400);
    }

    // Proses approval berdasarkan peran user
    if ($user->role->name == 'GA') {
        $managerId = $this->getManagerForStaff($submission->user_id);
        if ($managerId) {
            AdminApproval::create([
                'user_id' => $managerId,
                'submission_id' => $submissionId,
                'status' => 'pending'
            ]);

            $approval->update([
                'status' => 'approved',
                'notes' => $request->input('notes', null),
                'approved_at' => Carbon::now(),
            ]);
        }
    } elseif ($user->role->name == 'Manager') {
        AdminApproval::create([
            'user_id' => 1,
            'submission_id' => $submissionId,
            'status' => 'pending'
        ]);

        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);
    } elseif ($user->role->name == 'CEO') {
        AdminApproval::create([
            'user_id' => 7,
            'submission_id' => $submissionId,
            'status' => 'pending'
        ]);

        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);
    } elseif ($user->role->name == 'Finance') {
        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);

        $submission->update(['finish_status' => 'approved']);
    }

    return response()->json(["message" => "Approval berhasil"], 200);
}


    public function denied(Request $request, $submissionId)
{
    $user = Auth::user();
    $approval = AdminApproval::where('submission_id', $submissionId)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $approval->update([
        'status' => 'denied',
        'notes' => $request->input('notes', null),
        'approved_at' => Carbon::now(),
    ]);

    $submission = Submission::find($submissionId);
    $submission->update(['finish_status' => 'denied']);

    return response()->json(["message" => "Submission denied"], 200);
}

    
    private function getManagerForStaff($staffId)
    {

        $staff = Staff::where('staff_id', $staffId)->first();
        return $staff ? $staff->manager_id : null;
    }

    


    
}
