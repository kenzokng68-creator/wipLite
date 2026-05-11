<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;

use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::withCount('users')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        Role::create($validated);

        return redirect()->back()->with('success', 'Rôle créé avec succès.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
        ]);

        $role->update($validated);

        return redirect()->back()->with('success', 'Rôle mis à jour avec succès.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return redirect()->back()->with('error', 'Ce rôle ne peut pas être supprimé car il est assigné à des utilisateurs.');
        }

        $role->delete();

        return redirect()->back()->with('success', 'Rôle supprimé avec succès.');
    }
}
