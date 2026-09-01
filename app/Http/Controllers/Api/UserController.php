<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\ChecksPlanLimits;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use AuthorizesCompany, ChecksPlanLimits, HasRoleAndPermissions;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //$this->authorizePermission('create_user');

        // Check plan limit for users
        $limitCheck = $this->checkPlanLimit('users');
        if ($limitCheck) {
            return $limitCheck;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'admin' => 'boolean',
        ]);

        // Assign company from authenticated user
        $validated['company_id'] = $request->user()->company_id;
        $validated['password'] = Hash::make($validated['password']);

        // Default admin to false if not provided, or handle '1'/'0' logic if needed
        if (isset($validated['admin']) && $validated['admin']) {
            $validated['admin'] = '1';
        } else {
            $validated['admin'] = '0';
        }

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //$this->authorizePermission('view_users');

        $this->authorizeCompany($user);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //$this->authorizePermission('edit_user');

        $this->authorizeCompany($user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'admin' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['admin'])) {
            $validated['admin'] = $validated['admin'] ? '1' : '0';
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //$this->authorizePermission('delete_user');

        $this->authorizeCompany($user);
        $user->delete();

        return response()->json(null, 204);
    }
}
