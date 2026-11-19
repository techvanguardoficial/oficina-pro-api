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
        $this->ensureAdmin($request);

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

        $actor = $request->user();
        if (!$actor->isAdmin() && $actor->id !== $user->id) {
            abort(403, 'Apenas administradores podem alterar outros usuários.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'admin' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('admin', $validated)) {
            if (!$actor->isAdmin()) {
                abort(403, 'Apenas administradores podem alterar o nível de acesso.');
            }
            $validated['admin'] = $validated['admin'] ? '1' : '0';
            if ($validated['admin'] === '0' && $user->isAdmin()) {
                $this->ensureNotLastAdmin($user);
            }
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorizeCompany($user);
        $this->ensureAdmin($request);

        if ($request->user()->id === $user->id) {
            abort(422, 'Você não pode remover o seu próprio usuário.');
        }
        if ($user->isAdmin()) {
            $this->ensureNotLastAdmin($user);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    private function ensureAdmin(Request $request): void
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Apenas administradores podem gerenciar usuários.');
        }
    }

    private function ensureNotLastAdmin(User $user): void
    {
        $otherAdmins = User::where('company_id', $user->company_id)
            ->where('admin', '1')
            ->where('id', '!=', $user->id)
            ->exists();

        if (!$otherAdmins) {
            abort(422, 'A oficina precisa ter pelo menos um administrador.');
        }
    }
}
