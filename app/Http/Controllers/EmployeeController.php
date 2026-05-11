<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{


    /**
     * Liste des employés avec recherche et filtres
     */
    public function index(Request $request)
    {
        $query = Employee::with('position', 'user');

        // Filtrage selon la route
        $routeName = $request->route()->getName();

        if ($routeName === 'employees.assigned') {
            $query->whereHas('assignments', fn($q) => $q->where('status', 'actif'));
        } elseif ($routeName === 'employees.unassigned') {
            $query->whereDoesntHave('assignments', fn($q) => $q->where('status', 'actif'));
        } elseif ($routeName === 'employees.inactifs') {
            $query->where('status', 'inactif');
        }

        // Recherche globale
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        // Filtre manuel par statut si présent
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Tri et Pagination
        $sortField = $request->input('sort_field', 'last_name');
        $sortOrder = $request->input('sort_order', 'asc');
        $perPage = $request->input('per_page', 10);

        $employees = $query->orderBy($sortField, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'positions' => Position::all(),
            'filters'   => $request->only('search', 'status', 'position_id'),
            'currentView' => $routeName
        ]);
    }

    /**
     * Historique global — toutes les modifications de tous les employés
     */
    public function history(Request $request)
    {
        $histories = EmployeeHistory::with('employee', 'oldPosition', 'newPosition', 'changedBy')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Employees/History', [
            'histories' => $histories,
        ]);
    }


    /**
     * Formulaire de création
     */
    public function create()
    {
        return Inertia::render('Employees/Create', [
            'positions' => Position::all(),
            'statuses'  => Employee::$statuses,
        ]);
    }

    /**
     * Enregistrement d'un nouvel employé
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'     => ['nullable', 'exists:users,id'],
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'birth_date'  => ['required', 'date', 'before:today'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'email'       => ['required', 'email', 'unique:employees,email'],
            'address'     => ['nullable', 'string'],
            'position_id' => ['required', 'exists:positions,id'],
            'salary_base' => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:actif,inactif,suspendu'],
        ], [
            'first_name.required'  => 'Le prénom est obligatoire.',
            'last_name.required'   => 'Le nom est obligatoire.',
            'birth_date.required'  => 'La date de naissance est obligatoire.',
            'birth_date.before'    => 'La date de naissance doit être dans le passé.',
            'email.required'       => 'L\'email est obligatoire.',
            'email.unique'         => 'Cet email est déjà utilisé.',
            'position_id.required' => 'Le poste est obligatoire.',
            'salary_base.required' => 'Le salaire de base est obligatoire.',
            'status.required'      => 'Le statut est obligatoire.',
            'status.in'            => 'Le statut est invalide.',
        ]);

        $employee = Employee::create($validated);

        return redirect()
            ->route('employees.index')
            ->with('success', "L'employé {$employee->full_name} a été créé avec le matricule {$employee->matricule}.");
    }

    /**
     * Fiche détaillée d'un employé
     */
    public function show(Employee $employee)
    {
        $employee->load('position', 'user', 'histories.changedBy');
        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    /**
     * Formulaire de modification
     */
    public function edit(Employee $employee)
    {
        $employee->load('position', 'user');

        return Inertia::render('Employees/Edit', [
            'employee'  => $employee,
            'positions' => Position::all(),
            'statuses'  => Employee::$statuses,
        ]);
    }

    /**
     * Mise à jour d'un employé
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'user_id'     => ['nullable', 'exists:users,id'],
            'first_name'  => ['sometimes', 'string', 'max:100'],
            'last_name'   => ['sometimes', 'string', 'max:100'],
            'birth_date'  => ['sometimes', 'date', 'before:today'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'email'       => ['sometimes', 'email', 'unique:employees,email,' . $employee->id],
            'address'     => ['nullable', 'string'],
            'position_id' => ['sometimes', 'exists:positions,id'],
            'salary_base' => ['sometimes', 'numeric', 'min:0'],
            'status'      => ['sometimes', 'in:actif,inactif,suspendu'],
        ], [
            'birth_date.before'   => 'La date de naissance doit être dans le passé.',
            'email.unique'        => 'Cet email est déjà utilisé par un autre employé.',
            'position_id.exists'  => 'Le poste sélectionné est invalide.',
            'salary_base.numeric' => 'Le salaire doit être un nombre.',
            'status.in'           => 'Le statut est invalide.',
        ]);

        $employee->update($validated);

        return redirect()
            ->route('employees.index', $employee)
            ->with('success', "Les informations de {$employee->full_name} ont été mises à jour.");
    }

    /**
     * Suppression (soft delete)
     */
    public function destroy(Employee $employee)
    {
        $name = $employee->full_name;
        $employee->update(['status' => 'inactif']);

        return redirect()
            ->route('employees.index')
            ->with('success', "L'employé {$name} a été désactivé.");
    }
}
