<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Department =     Department::all();

        return response()->json([
        'message' => 'All Departments retrieved successfully',
        'data' =>  $Department
    ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(['title' => 'required']);
    $Department = Department::create($request->all());
    
    return response()->json([
        'message' => 'Department created successfully',
        'data' => $Department
    ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $Department = Department::findOrFail($id);
    
        return response()->json([
            'message' => 'Department retrieved successfully',
            'data' => $Department
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $Department = Department::findOrFail($id);
    $Department->update($request->all());
    
    return response()->json([
        'message' => 'Department updated successfully',
        'data' => $Department
    ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $Department = Department::findOrFail($id);
    $Department->delete();
    
    return response()->json([
        'message' => 'Department deleted successfully'
    ]);
    }
}
