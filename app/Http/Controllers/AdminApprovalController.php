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

    if ($role === 'GA') {
        $submissions = Submission::whereHas('adminApprovals',  function($query) use ($user) {
            $query->where('status', 'pending')
                  ->where('user_id', $user->id); 
        })->with('adminApprovals', 'items', 'files')->get();
    }

    elseif ($role === 'Manager') {
        $staffIds = Staff::where('manager_id', $user->id)->pluck('staff_id');

        $submissions = Submission::whereIn('user_id', $staffIds)
            ->whereHas('adminApprovals', function($query) use ($user) {
                $query->where('status', 'pending')
                      ->where('user_id', $user->id); 
            })->with('adminApprovals')->get();
    }

    elseif ($role === 'CEO') {
        $submissions = Submission::whereHas('adminApprovals', function($query) use ($user) {
            $query->where('status', 'pending')
                  ->where('user_id', $user->id); 
        })->with('adminApprovals')->get();
    }

    elseif ($role === 'Finance') {
        $submissions = Submission::whereHas('adminApprovals', function($query) use ($user) {
            $query->where('status', 'pending')
                  ->where('user_id', $user->id); 
        })->with('adminApprovals')->get();
    }elseif($role === 'Employee'){
        $submissions = 'Sory Your login not admin';
    }

    return response()->json([
        'submissions' => $submissions
    ], 200);
}


    public function approve(Request $request, $submissionId)
    {
        $user = Auth::user();
        $approval = AdminApproval::where('submission_id', $submissionId)
            ->where('user_id', $user->id)
            ->firstOrFail();
    
        $approval->update([
            'status' => 'approved',
            'notes' => $request->input('notes', null),
            'approved_at' => Carbon::now(),
        ]);
    

        if ($user->role->name == 'GA') {
            $submission = Submission::find($submissionId);
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
            $submission = Submission::find($submissionId);

            $approval->update([
                'status' => 'approved',
                'notes' => $request->input('notes', null),
                'approved_at' => Carbon::now(),
            ]);
            
            $submission->update(['finish_status' => 'approved']);
        }
    
        return response()->json(["message" => "Approval berhasil"], 200);
    }
    
    private function getManagerForStaff($staffId)
    {

        $staff = Staff::where('staff_id', $staffId)->first();
        return $staff ? $staff->manager_id : null;
    }

    


    
}
