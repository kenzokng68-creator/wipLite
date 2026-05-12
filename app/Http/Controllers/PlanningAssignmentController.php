<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Assignment;
use App\Models\PlanningModel;
use App\Models\PlanningAssignment;
use App\Models\PlanningHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

use App\Models\Campaign;
use App\Models\Position;

class PlanningAssignmentController extends Controller
{
    /**
     * Affiche la liste globale des affectations de planning.
     * Réservé aux Admins et Chefs de Plateau (CP).
     * Organise les données par superviseur et inclut les agents de leur équipe.
     */
    public function index()
    {
        // Redirection si l'utilisateur n'a pas les droits de gestion globale
        if (!auth()->user()->hasRole(['admin', 'cp'])) {
            return redirect()->route('planning.mine');
        }

        // Récupération de toutes les affectations avec les relations nécessaires
        $allAssignments = PlanningAssignment::with(['employee.user.role', 'planningModel', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Liste des superviseurs pour structurer l'affichage par équipe
        $supervisors = Employee::with(['user.role', 'position'])
            ->whereHas('position', function ($query) {
                $query->where('code', 'SUP');
            })
            ->get();

        // Récupérer les chefs de plateau pour l'affectation des superviseurs
        $chefsDePlateau = Employee::whereHas('user.role', function($q) {
            $q->where('name', 'cp');
        })->get();

        // Récupérer le position_id pour SUP
        $supPosition = Position::where('code', 'SUP')->first();

        // Structuration des données : Superviseur -> Ses Plannings + Plannings de son équipe (Agents)
        $supervisorAssignments = $supervisors->map(function ($supervisor) use ($allAssignments) {
            $supervisorAssignments = $allAssignments->where('employee_id', $supervisor->id);

            // Vérifier si le superviseur a une affectation de campagne active
            $campaignAssignment = Assignment::with('campaign')
                ->where('employee_id', $supervisor->id)
                ->where('status', 'actif')
                ->first();

            // Récupération des agents (TC) actuellement sous la responsabilité de ce superviseur
            $teleconseillerIds = Assignment::where('manager_id', $supervisor->id)
                ->where('status', 'actif')
                ->pluck('employee_id');

            // Récupérer tous les membres de l'équipe même si le superviseur n'a pas de planning
            $allTeamMembers = Employee::with('user.role')
                ->whereIn('id', $teleconseillerIds)
                ->get();

            // Plannings des membres de l'équipe
            $teamPlannings = $allAssignments->filter(function($a) use ($teleconseillerIds) {
                return in_array($a->employee_id, $teleconseillerIds->toArray());
            });

            $teleconseillers = $allTeamMembers->map(function($emp) use ($teamPlannings) {
                $p = $teamPlannings->firstWhere('employee_id', $emp->id);
                return [
                    'id' => $p?->id,
                    'employee' => [
                        'name' => $emp->user ? $emp->user->name : $emp->full_name,
                        'role' => $emp->user ? $emp->user->role?->name : 'TC',
                    ],
                    'model' => ['name' => $p?->planningModel?->name ?? 'Aucun planning'],
                    'start_date' => $p?->start_date ? $p->start_date->format('d/m/Y') : 'N/A',
                    'end_date' => $p?->end_date ? $p->end_date->format('d/m/Y') : 'N/A',
                    'status' => $p?->status ?? 'non assigné',
                    'has_planning' => $p !== null,
                ];
            })->values();

            return [
                'supervisor' => [
                    'id' => $supervisor->id,
                    'name' => $supervisor->user ? $supervisor->user->name : $supervisor->full_name,
                    'has_campaign' => $campaignAssignment !== null,
                    'campaign_name' => $campaignAssignment?->campaign?->name,
                ],
                'assignments' => $supervisorAssignments->values()->map(fn($a) => [
                    'id' => $a->id,
                    'model' => ['name' => $a->planningModel?->name ?? 'N/A'],
                    'start_date' => $a->start_date ? $a->start_date->format('d/m/Y') : 'N/A',
                    'end_date' => $a->end_date ? $a->end_date->format('d/m/Y') : 'N/A',
                    'status' => $a->status,
                ]),
                'teleconseillers' => $teleconseillers,
            ];
        })->values();

        return Inertia::render('Planning/Assignments/Index', [
            'supervisorAssignments' => $supervisorAssignments,
            'campaigns' => Campaign::where('status', 'active')->get(),
            'chefsDePlateau' => $chefsDePlateau,
            'supPositionId' => $supPosition?->id
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Planning/Assignments/Create', [
            'selected_supervisor_id' => $request->query('supervisor_id'),
            'supervisors' => Employee::with(['user.role', 'position'])
                            ->whereHas('position', function ($query) {
                                $query->where('code', 'SUP');
                            })
                            ->get()
                            ->map(fn($emp) => [
                                'id' => $emp->id,
                                'name' => $emp->user ? $emp->user->name : $emp->full_name,
                                'role' => $emp->user ? $emp->user->role?->name : 'SUP',
                            ]),
            'models' => PlanningModel::all(['id', 'name', 'total_hours'])
        ]);
    }

    /**
     * Gère la création d'une nouvelle affectation.
     * Lorsqu'un modèle est affecté à un superviseur, il est automatiquement
     * propagé à tous les agents de son équipe active.
     * Les TC ne peuvent avoir un planning que si leur superviseur en a un.
     */
    public function store(Request $request)
    {
        $request->validate([
            'supervisor_id' => 'required|exists:employees,id',
            'planning_model_id' => 'required|exists:planning_models,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $supervisor = Employee::findOrFail($request->supervisor_id);

        // Identification automatique des agents sous la responsabilité du superviseur
        $teleconseillerIds = Assignment::where('manager_id', $supervisor->id)
            ->where('status', 'actif')
            ->pluck('employee_id');

        // Liste de tous les bénéficiaires (Superviseur + ses Agents)
        $employeesToAssignIds = collect([$supervisor->id])->merge($teleconseillerIds);

        // Suppression des plannings existants sur la même période pour tous les bénéficiaires
        // (Écrase les anciens plannings comme demandé)
        PlanningAssignment::whereIn('employee_id', $employeesToAssignIds)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->delete();

        $employeesToAssign = Employee::whereIn('id', $employeesToAssignIds)->get();

        foreach ($employeesToAssign as $employee) {
            // Création de l'affectation avec le statut 'en attente' par défaut
            $assignment = PlanningAssignment::create([
                'employee_id' => $employee->id,
                'planning_model_id' => $request->planning_model_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => Auth::id(),
                'status' => 'en attente',
            ]);

            // Enregistrement dans l'historique pour traçabilité
            PlanningHistory::create([
                'planning_assignment_id' => $assignment->id,
                'old_status' => '',
                'new_status' => 'en attente',
                'changed_by' => Auth::id(),
                'reason' => $employee->id === $supervisor->id 
                    ? 'Création de l\'affectation superviseur (Écrasement si existant)' 
                    : 'Héritage automatique du planning superviseur (Écrasement si existant)',
            ]);
        }

        return redirect()->route('planning.assignments.index')
            ->with('success', 'Affectations créées avec succès (les anciens plannings sur cette période ont été remplacés).');
    }

    /**
     * Valide une affectation spécifique pour la rendre effective.
     * Lorsqu'on valide un superviseur, on valide aussi ses agents automatiquement.
     */
    public function validateAssignment(Request $request, $id)
    {
        $assignment = PlanningAssignment::findOrFail($id);
        
        $this->validateAssignmentAndLinkedAssignments($assignment);

        return back()->with('success', 'Le planning est désormais effectif.');
    }

    /**
     * Valide un lot d'affectations et leurs assignments liées (agents).
     */
    public function bulkValidate(Request $request)
    {
        $ids = $request->input('ids', []);

        $assignments = PlanningAssignment::whereIn('id', $ids)
            ->where('status', 'en attente')
            ->get();

        foreach ($assignments as $assignment) {
            $this->validateAssignmentAndLinkedAssignments($assignment);
        }

        return back()->with('success', count($assignments) . ' plannings ont été validés.');
    }

    /**
     * Valide toutes les affectations en attente (superviseurs) et leurs agents.
     */
    public function validateAll()
    {
        $assignments = PlanningAssignment::where('status', 'en attente')
            ->whereHas('employee.user.role', function ($query) {
                $query->where('name', 'sup');
            })
            ->get();

        foreach ($assignments as $assignment) {
            $this->validateAssignmentAndLinkedAssignments($assignment);
        }

        return back()->with('success', count($assignments) . ' plannings ont été validés.');
    }

    /**
     * Valide une affectation et les affectations des agents liés au même superviseur et planning.
     */
    private function validateAssignmentAndLinkedAssignments($assignment)
    {
        $oldStatus = $assignment->status;

        $assignment->update([
            'status' => 'validé',
            'validated_at' => now(),
            'validated_by' => Auth::id()
        ]);

        PlanningHistory::create([
            'planning_assignment_id' => $assignment->id,
            'old_status' => $oldStatus,
            'new_status' => 'validé',
            'changed_by' => Auth::id(),
            'reason' => 'Validation du planning',
        ]);

        // Si c'est un superviseur, valider aussi les affectations de ses agents
        if ($assignment->employee->user->role->name === 'sup') {
            $teleconseillerIds = Assignment::where('manager_id', $assignment->employee_id)
                ->where('status', 'actif')
                ->pluck('employee_id');

            $linkedAssignments = PlanningAssignment::whereIn('employee_id', $teleconseillerIds)
                ->where('planning_model_id', $assignment->planning_model_id)
                ->where('start_date', $assignment->start_date)
                ->where('end_date', $assignment->end_date)
                ->where('status', 'en attente')
                ->get();

            foreach ($linkedAssignments as $linkedAssignment) {
                $linkedOldStatus = $linkedAssignment->status;
                $linkedAssignment->update([
                    'status' => 'validé',
                    'validated_at' => now(),
                    'validated_by' => Auth::id()
                ]);
                PlanningHistory::create([
                    'planning_assignment_id' => $linkedAssignment->id,
                    'old_status' => $linkedOldStatus,
                    'new_status' => 'validé',
                    'changed_by' => Auth::id(),
                    'reason' => 'Validation automatique (héritage du superviseur)',
                ]);
            }
        }
    }

    public function suspendAssignment(Request $request, $id)
    {
        $assignment = PlanningAssignment::findOrFail($id);
        $oldStatus = $assignment->status;

        $assignment->update([
            'status' => 'suspendu',
        ]);

        PlanningHistory::create([
            'planning_assignment_id' => $assignment->id,
            'old_status' => $oldStatus,
            'new_status' => 'suspendu',
            'changed_by' => Auth::id(),
            'reason' => $request->input('reason', 'Suspension du planning'),
        ]);

        return back()->with('success', 'Le planning a été suspendu.');
    }

    public function terminateAssignment($id)
    {
        $assignment = PlanningAssignment::findOrFail($id);
        $oldStatus = $assignment->status;

        $assignment->update([
            'status' => 'terminé',
        ]);

        PlanningHistory::create([
            'planning_assignment_id' => $assignment->id,
            'old_status' => $oldStatus,
            'new_status' => 'terminé',
            'changed_by' => Auth::id(),
            'reason' => 'Terminaison du planning',
        ]);

        return back()->with('success', 'Le planning a été terminé.');
    }

    public function validation()
    {
        $pendingAssignments = PlanningAssignment::with(['employee.user.role', 'planningModel'])
            ->where('status', 'en attente')
            ->whereHas('employee.user.role', function ($query) {
                $query->where('name', 'sup');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'employee' => [
                    'name' => $a->employee->user->name,
                    'role' => $a->employee->user->role?->name ?? 'N/A',
                ],
                'model' => ['name' => $a->planningModel->name],
                'start_date' => $a->start_date->format('d/m/Y'),
                'end_date' => $a->end_date->format('d/m/Y'),
                'status' => $a->status,
            ]);

        return Inertia::render('Planning/Assignments/Validation', [
            'pendingAssignments' => $pendingAssignments
        ]);
    }

    /**
     * Affiche l'historique complet des changements de statut des plannings.
     * Le contenu est filtré selon le rôle de l'utilisateur (TC, SUP, Admin/CP).
     */
    public function history()
    {
        $query = PlanningHistory::with(['planningAssignment.employee.user', 'changedBy'])
            ->orderBy('created_at', 'desc');

        // Filtrage de sécurité : les agents et superviseurs ne voient que ce qui les concerne
        if (!auth()->user()->hasRole(['admin', 'cp'])) {
            $employeeId = auth()->user()->employee->id;

            if (auth()->user()->hasRole('sup')) {
                // Le superviseur voit son historique + celui des agents sous sa responsabilité
                $agentIds = Assignment::where('manager_id', $employeeId)
                    ->where('status', 'actif')
                    ->pluck('employee_id');

                $ids = $agentIds->push($employeeId);

                $query->whereHas('planningAssignment', function($q) use ($ids) {
                    $q->whereIn('employee_id', $ids);
                });
            } else {
                // Le Téléconseiller (TC) ne voit strictement que son propre historique
                $query->whereHas('planningAssignment', function($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId);
                });
            }
        }

        $history = $query->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'planning_assignment' => [
                    'employee_name' => $h->planningAssignment->employee->user->name ?? 'Inconnu',
                ],
                'old_status' => $h->old_status,
                'new_status' => $h->new_status,
                'changed_by' => $h->changedBy->name ?? 'Système',
                'reason' => $h->reason,
                'created_at' => $h->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Planning/Assignments/History', [
            'history' => $history
        ]);
    }

    /**
     * Vue personnelle pour chaque employé afin de consulter son planning validé.
     */
    public function mine()
    {
        $employee = auth()->user()->employee;

        $assignments = PlanningAssignment::with(['planningModel', 'creator'])
            ->where('employee_id', $employee->id)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'model' => ['name' => $a->planningModel?->name ?? 'N/A'],
                'start_date' => $a->start_date ? $a->start_date->format('d/m/Y') : 'N/A',
                'end_date' => $a->end_date ? $a->end_date->format('d/m/Y') : 'N/A',
                'status' => $a->status,
                'creator' => $a->creator->name ?? 'Système',
            ]);

        return Inertia::render('Planning/Mine', [
            'assignments' => $assignments
        ]);
    }

    /**
     * Vue spécifique pour le superviseur lui permettant de voir les plannings de son équipe.
     */
    public function team()
    {
        // Sécurité supplémentaire : seuls les superviseurs peuvent accéder à cette vue
        if (!auth()->user()->hasRole('sup')) {
            return redirect()->route('planning.mine');
        }

        $managerId = auth()->user()->employee->id;

        // Identification des agents de l'équipe
        $agentIds = Assignment::where('manager_id', $managerId)
            ->where('status', 'actif')
            ->pluck('employee_id');

        $allAgents = Employee::with('user.role')
            ->whereIn('id', $agentIds)
            ->get();

        $assignments = PlanningAssignment::with(['employee.user', 'planningModel'])
            ->whereIn('employee_id', $agentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $teamData = $allAgents->map(function($agent) use ($assignments) {
            $assignment = $assignments->where('employee_id', $agent->id)->first();
            
            return [
                'id' => $assignment?->id,
                'employee' => [
                    'name' => $agent->user ? $agent->user->name : $agent->full_name,
                    'role' => $agent->user ? $agent->user->role?->name : 'TC',
                ],
                'model' => ['name' => $assignment?->planningModel?->name ?? 'Aucun planning'],
                'start_date' => $assignment?->start_date ? $assignment->start_date->format('d/m/Y') : 'N/A',
                'end_date' => $assignment?->end_date ? $assignment->end_date->format('d/m/Y') : 'N/A',
                'status' => $assignment?->status ?? 'non assigné',
                'has_planning' => $assignment !== null,
            ];
        });

        return Inertia::render('Planning/Team', [
            'assignments' => $teamData
        ]);
    }

    public function destroy($id)
    {
        $assignment = PlanningAssignment::findOrFail($id);
        $assignment->delete();

        return back()->with('success', 'Affectation supprimée.');
    }
}
