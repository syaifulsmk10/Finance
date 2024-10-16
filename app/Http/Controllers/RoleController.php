<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return response()->json([
            'data' => $roles, 
            'message' => 'Roles retrieved successfully'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        $role = Role::create($request->all());
        return response()->json([
            'data' => $role, 
            'message' => 'Role created successfully'], 201);
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);
        return response()->json([
            'data' => $role, 
            'message' => 'Role retrieved successfully']);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        if ($request->has('name')) {
            $role->name = $request->name;
        }

        if ($request->has('level')) {
            $role->level = $request->level;
        }

        $role->save();

        return response()->json([
            'message' => 'Role updated successfully']);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return response()->json([
            'message' => 'Role deleted successfully']);
    }
}
