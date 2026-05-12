<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    use \App\Traits\LogsActivity;
    // use App\LogsActivity;
    public function index()
    {
        // On récupère TOUS les utilisateurs pour le filtrage automatique côté client (PrimeVue)
        $users = User::with('role')->get();
        return Inertia::render('Users/Index', [
            'users' => $users
        ]);
    }

    public function create()
    {
        $roles = Role::all();
        // Employés qui n'ont pas encore de compte utilisateur
        $employees = \App\Models\Employee::whereNull('user_id')->get(['id', 'email', 'first_name', 'last_name']);
        
        return Inertia::render('Users/Create', [
            'roles' => $roles,
            'employees' => $employees
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        // Lier l'employé s'il existe
        $employee = \App\Models\Employee::where('email', $request->email)->first();
        if ($employee) {
            $employee->update(['user_id' => $user->id]);
        }

        $this->logActivity(
            'create',
            'User',
            $user->id,
            'Création de l’utilisateur ' . $user->email
        );

        return redirect()->route('users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $user->load('employee');
        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update([
            'email' => $request->email,
            'role_id' => $request->role_id,
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8',
            ]);
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Mettre à jour le user_id de l'employé si l'email a changé ? 
        // Normalement l'email est unique et lié à l'employé.
        $employee = \App\Models\Employee::where('email', $request->email)->first();
        if ($employee && $employee->user_id !== $user->id) {
            $employee->update(['user_id' => $user->id]);
        }

        $this->logActivity(
            'update',
            'User',
            $user->id,
            'Modification de l’utilisateur ' . $user->email
        );

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour avec succès.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        $this->logActivity(
            'delete',
            'User',
            $user->id,
            'Suppression de l’utilisateur ' . $user->name
        );
        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé avec succès.');
    }
}
