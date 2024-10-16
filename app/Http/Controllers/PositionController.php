<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Role;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index()
{
    $position = Position::all();

    return response()->json([
        'message' => 'All positions retrieved successfully',
        'data' =>  $position
    ], 200);
}

public function store(Request $request)
{
    $request->validate(['title' => 'required']);
    $position = Position::create($request->all());
    
    return response()->json([
        'message' => 'Position created successfully',
        'data' => $position
    ], 201);
}

public function show($id)
{
    $position = Position::findOrFail($id);
    
    return response()->json([
        'message' => 'Position retrieved successfully',
        'data' => $position
    ], 200);
}

public function update(Request $request, $id)
{
    $position = Position::findOrFail($id);
    $position->update($request->all());
    
    return response()->json([
        'message' => 'Position updated successfully',
        'data' => $position
    ], 200);
}

public function destroy($id)
{
    $position = Position::findOrFail($id);
    $position->delete();
    
    return response()->json([
        'message' => 'Position deleted successfully'
    ]);
}

}
