<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    public function manager(Request $request)
{
    $position_id = $request->query('position_id'); // Ambil parameter position_id dari request

    if (in_array($position_id, [1, 2, 3, 4])) {
        return response()->json([
            'data' => [] // Tidak ada manager untuk position_id 1, 2, 3, 4
        ]);
    }

    $managers = User::whereHas('role', function ($query) {
        $query->where('name', 'Manager');
    })->get();

    return response()->json([
        'data' => $managers
    ]);
}



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Staff $staff)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staff $staff)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Staff $staff)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Staff $staff)
    {
        //
    }
}
