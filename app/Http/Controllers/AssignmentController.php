<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentHistory;
use App\Models\Campaign;
use App\Models\Employee;
use App\Models\Position;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;

class AssignmentController extends Controller
{
    /**
     * Affiche la liste des affectations selon le contexte
     */
    public function index(Request $request)
    {
        $view = $request->route()->getName();
        
        $query = Assignment::with(['employee', 'campaign', 'manager', 'position'])
            ->where('status', 'actif');

        // Selon la route, on peut filtrer
        if ($view === 'assignments.cp') {
            $query->whereHas('position', fn($q) => $q->where('code', 'CP'));
        } elseif ($view === 'assignments.sup') {
            $query->whereHas('position', fn($q) => $q->where('code', 'SUP'));
        } elseif ($view === 'assignments.tc') {
            $query->whereHas('position', fn($q) => $q->where('code', 'TC'));
        }

        $assignments = $query->latest()->get();

        return Inertia::render('Assignments/Index', [
            'assignments' => $assignments,
            'employees'   => Employee::where('status', 'actif')->get(),
            'campaigns'   => Campaign::where('status', 'active')->get(),
            'positions'   => Position::all(),
            'currentView' => $view
        ]);
    }

    /**
     * Affiche la vue hiérarchique des affectations
     */
    public function hierarchy()
    {
        // On récupère toutes les campagnes actives avec leurs affectations imbriquées
        // Campagne -> CP (manager_id null et position CP) -> SUP (manager_id CP) -> TC (manager_id SUP)
        $hierarchy = Campaign::where('status', 'active')
            ->with(['assignments' => function($query) {
                $query->where('status', 'actif')
                    ->with(['employee.position', 'position']);
            }])
            ->get()
            ->map(function($campaign) {
                // On structure les données pour le front-end
                $assignments = $campaign->assignments;
                
                // 1. Trouver les Chefs de Plateau (CP) de la campagne
                $cps = $assignments->filter(function($a) {
                    return $a->position->code === 'CP';
                })->map(function($cp) use ($assignments) {
                    // 2. Pour chaque CP, trouver ses Superviseurs (SUP)
                    $supervisors = $assignments->filter(function($a) use ($cp) {
                        return $a->position->code === 'SUP' && $a->manager_id === $cp->employee_id;
                    })->map(function($sup) use ($assignments) {
                        // 3. Pour chaque SUP, trouver ses Téléconseillers (TC)
                        $tcs = $assignments->filter(function($a) use ($sup) {
                            return $a->position->code === 'TC' && $a->manager_id === $sup->employee_id;
                        });
                        
                        $sup->teleconseillers = $tcs->values();
                        return $sup;
                    });
                    
                    $cp->supervisors = $supervisors->values();
                    return $cp;
                });

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'cps' => $cps->values()
                ];
            });

        return Inertia::render('Assignments/Hierarchy', [
            'hierarchy' => $hierarchy
        ]);
    }

    /**
     * Affiche l'historique des affectations
     */
    public function history()
    {
        $histories = AssignmentHistory::with(['employee', 'oldManager', 'newManager', 'oldCampaign', 'newCampaign', 'author'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Assignments/History', [
            'histories' => $histories
        ]);
    }

    /**
     * =========================================================
     * PAGE D'AFFECTATION DES CP
     * =========================================================
     */
    public function assignCP()
    {
        /**
         * =====================================================
         * POSITION CP
         * =====================================================
         *
         * On récupère la position CP
         * pour filtrer correctement.
         */
        $cpPosition = Position::where('code', 'CP')->first();

        /**
         * =====================================================
         * CP DÉJÀ AFFECTÉS
         * =====================================================
         *
         * Ici :
         * - toutes les affectations actives
         * - dont la position = CP
         */
        $assignedCPs = Assignment::with([

            /**
             * Employé lié
             */
            'employee.user.role',

            /**
             * Campagne liée
             */
            'campaign',

            /**
             * Position
             */
            'position'
        ])

            /**
             * Affectations actives uniquement
             */
            ->where('status', 'actif')

            /**
             * Position CP uniquement
             */
            ->where('position_id', $cpPosition->id)

            /**
             * Tri récent
             */
            ->latest()

            ->get();



        /**
         * =====================================================
         * IDS DES CP DÉJÀ AFFECTÉS
         * =====================================================
         *
         * IMPORTANT :
         * Dès qu'un CP possède UNE affectation,
         * il ne doit plus apparaître
         * dans la liste des CP disponibles.
         */
        $assignedEmployeeIds = Assignment::where('status', 'actif')

            /**
             * Affectations CP uniquement
             */
            ->where('position_id', $cpPosition->id)

            /**
             * On récupère seulement employee_id
             */
            ->pluck('employee_id');



        /**
         * =====================================================
         * CP NON AFFECTÉS
         * =====================================================
         *
         * On récupère :
         * - les employés
         * - ayant le rôle cp
         * - ET n'ayant aucune affectation CP active
         */
        $unassignedCPs = Employee::with([
            'user.role'
        ])

            /**
             * Vérifie le rôle CP
             */
            ->whereHas('user.role', function ($query) {

                $query->where('name', 'cp');
            })

            /**
             * Exclure les CP déjà affectés
             */
            ->whereNotIn('id', $assignedEmployeeIds)

            /**
             * Tri récent
             */
            ->latest()

            ->get();



        /**
         * =====================================================
         * CAMPAGNES DISPONIBLES
         * =====================================================
         *
         * RÈGLE :
         * Une campagne
         * = un seul CP actif
         *
         * Donc :
         * On récupère uniquement
         * les campagnes sans affectation CP active.
         */
        $campaigns = Campaign::where('status', 'active')

            /**
             * Exclure campagnes
             * ayant déjà un CP actif
             */
            ->whereDoesntHave('assignments', function ($query) use ($cpPosition) {

                $query->where('position_id', $cpPosition->id)
                    ->where('status', 'actif');
            })

            /**
             * Tri récent
             */
            ->latest()

            ->get();



        /**
         * =====================================================
         * RETOUR INERTIA
         * =====================================================
         */
        return Inertia::render('Assignments/AssignCP', [

            /**
             * CP disponibles
             */
            'unassignedCPs' => $unassignedCPs,

            /**
             * CP affectés
             */
            'assignedCPs' => $assignedCPs,

            /**
             * Campagnes disponibles
             */
            'campaigns' => $campaigns,

            /**
             * Campagnes disponibles
             * pour ajout à un CP
             */
            'availableCampaigns' => $campaigns,
        ]);
    }

    /**
     * =========================================================
     * PAGE D'AFFECTATION DES SUPERVISEURS
     * =========================================================
     */
    public function assignSUP()
    {
        $supPosition = Position::where('code', 'SUP')->first();
        $cpPosition = Position::where('code', 'CP')->first();

        /**
         * SUP NON AFFECTÉS
         */
        $unassignedSUPs = Employee::with('user.role')
            ->whereHas('user.role', fn($q) => $q->where('name', 'sup'))
            ->whereDoesntHave('assignments', function ($q) use ($supPosition) {
                $q->where('position_id', $supPosition->id)
                    ->where('status', 'actif');
            })
            ->get();

        /**
         * SUP AFFECTÉS
         */
        $assignedSUPs = Assignment::with([
            'employee.user.role',
            'campaign',
            'manager'
        ])
            ->where('position_id', $supPosition->id)
            ->where('status', 'actif')
            ->get();

        /**
         * CP DISPONIBLES (SUP doit dépendre d’un CP actif)
         */
        $cpAssignments = Assignment::with([
            'employee',
            'campaign'
        ])
            ->where('position_id', $cpPosition->id)
            ->where('status', 'actif')
            ->get();

        return Inertia::render('Assignments/AssignSUP', [
            'unassignedSUPs' => $unassignedSUPs,
            'assignedSUPs' => $assignedSUPs,
            'cpAssignments' => $cpAssignments,
        ]);
    }

    /**
     * =========================================================
     * AFFECTER UN SUP À UN CP
     * =========================================================
     */
    public function storeSUP(Request $request)
    {
        /**
         * =========================================
         * VALIDATION
         * =========================================
         */
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'cp_assignment_id' => 'required|exists:assignments,id',
        ]);



        /**
         * =========================================
         * POSITION SUP
         * =========================================
         */
        $supPosition = Position::where('code', 'SUP')->firstOrFail();



        /**
         * =========================================
         * EMPLOYÉ
         * =========================================
         */
        $employee = Employee::with('user.role')
            ->findOrFail($validated['employee_id']);



        if ($employee->user?->role?->name !== 'sup') {
            return back()->withErrors([
                'employee' => "Cet employé n'est pas un SUP"
            ]);
        }



        /**
         * =========================================
         * CP ASSIGNMENT
         * =========================================
         */
        $cpAssignment = Assignment::with(['employee', 'campaign'])
            ->findOrFail($validated['cp_assignment_id']);



        /**
         * =========================================
         * CRÉATION SUP + HISTORY
         * =========================================
         */
        DB::transaction(function () use ($employee, $cpAssignment, $supPosition) {

            /**
             * -----------------------------------------
             * 1. CREATE ASSIGNMENT SUP
             * -----------------------------------------
             */
            $newAssignment = Assignment::create([

                'employee_id' => $employee->id,
                'campaign_id' => $cpAssignment->campaign_id,
                'position_id' => $supPosition->id,

                /**
                 * SUP dépend du CP (hiérarchie)
                 */
                'manager_id' => $cpAssignment->employee_id,

                'status' => 'actif',
                'start_date' => now(),
            ]);



            /**
             * -----------------------------------------
             * 2. HISTORY
             * -----------------------------------------
             */
            AssignmentHistory::create([

                'assignment_id' => $newAssignment->id,
                'employee_id' => $employee->id,

                'old_manager_id' => null,
                'new_manager_id' => $cpAssignment->employee_id,

                'old_campaign_id' => null,
                'new_campaign_id' => $cpAssignment->campaign_id,

                /**
                 * IMPORTANT :
                 * doit correspondre à ton ENUM
                 */
                'action_type' => 'assign',

                'changed_by' => Auth::id(),

                'reason' => 'Affectation SUP sur CP',
            ]);
        });



        return back()->with('success', 'SUP affecté avec succès');
    }


    /**
     * =========================================================
     * AFFECTER UN CP À UNE CAMPAGNE
     * =========================================================
     */
    public function storeCP(Request $request)
    {
        /**
         * =====================================================
         * VALIDATION
         * =====================================================
         */
        $validated = $request->validate([

            /**
             * Employé obligatoire
             */
            'employee_id' => 'required|exists:employees,id',

            /**
             * Campagne obligatoire
             */
            'campaign_id' => 'required|exists:campaigns,id',
        ]);



        /**
         * =====================================================
         * POSITION CP
         * =====================================================
         */
        $cpPosition = Position::where('code', 'CP')->first();

        /**
         * Sécurité
         */
        if (!$cpPosition) {

            return back()->withErrors([
                'position' => 'Position CP introuvable'
            ]);
        }



        /**
         * =====================================================
         * EMPLOYÉ
         * =====================================================
         */
        $employee = Employee::with([
            'user.role'
        ])

            ->findOrFail($validated['employee_id']);



        /**
         * =====================================================
         * VÉRIFICATION ROLE CP
         * =====================================================
         */
        if ($employee->user?->role?->name !== 'cp') {

            return back()->withErrors([
                'employee' => "Cet employé n'est pas un CP"
            ]);
        }



        /**
         * =====================================================
         * CAMPAGNE DÉJÀ OCCUPÉE ?
         * =====================================================
         *
         * Une campagne
         * ne peut avoir
         * QU'UN seul CP actif.
         */
        $campaignAlreadyHasCP = Assignment::where('campaign_id', $validated['campaign_id'])

            /**
             * Affectation active
             */
            ->where('status', 'actif')

            /**
             * Position CP
             */
            ->where('position_id', $cpPosition->id)

            /**
             * Vérifie existence
             */
            ->exists();



        /**
         * Si déjà occupée
         */
        if ($campaignAlreadyHasCP) {

            return back()->withErrors([
                'campaign' => 'Cette campagne possède déjà un CP actif'
            ]);
        }



        /**
         * =====================================================
         * TRANSACTION
         * =====================================================
         *
         * Affectation + historique
         */
        DB::transaction(function () use (

            $employee,
            $validated,
            $cpPosition

        ) {

            /**
             * ================================================
             * CRÉATION AFFECTATION
             * ================================================
             */
            $assignment = Assignment::create([

                /**
                 * Employé
                 */
                'employee_id' => $employee->id,

                /**
                 * Campagne
                 */
                'campaign_id' => $validated['campaign_id'],

                /**
                 * Position CP
                 */
                'position_id' => $cpPosition->id,

                /**
                 * Statut
                 */
                'status' => 'actif',

                /**
                 * Date début
                 */
                'start_date' => now(),

                /**
                 * Le CP n'a pas de manager
                 */
                'manager_id' => null,
            ]);



            /**
             * ================================================
             * HISTORIQUE
             * ================================================
             */
            AssignmentHistory::create([

                /**
                 * Affectation liée
                 */
                'assignment_id' => $assignment->id,

                /**
                 * Employé
                 */
                'employee_id' => $employee->id,

                /**
                 * Ancien manager
                 */
                'old_manager_id' => null,

                /**
                 * Nouveau manager
                 */
                'new_manager_id' => null,

                /**
                 * Ancienne campagne
                 */
                'old_campaign_id' => null,

                /**
                 * Nouvelle campagne
                 */
                'new_campaign_id' => $validated['campaign_id'],

                /**
                 * Type action
                 */
                'action_type' => 'assign',

                /**
                 * Utilisateur connecté
                 */
                'changed_by' => Auth::id(),

                /**
                 * Raison
                 */
                'reason' => 'Affectation du CP à une campagne',
            ]);
        });



        /**
         * =====================================================
         * RETOUR
         * =====================================================
         */
        return back()->with('success', 'CP affecté avec succès');
    }


    /**
     * =========================================================
     * AFFECTER UN CP À UNE AUTRE CAMPAGNE
     * =========================================================
     *
     * Cette méthode permet :
     * - de prendre un CP déjà affecté
     * - et de lui attribuer une nouvelle campagne
     *
     * IMPORTANT :
     * - un CP peut avoir plusieurs campagnes
     * - une campagne ne peut avoir qu'un seul CP actif
     */
    public function assignNewCampaign(
        Request $request,
        Assignment $assignment
    ) {

        /**
         * =====================================================
         * VALIDATION
         * =====================================================
         */

        $validated = $request->validate([

            /**
             * Nouvelle campagne
             */
            'campaign_id' => 'required|exists:campaigns,id',
        ]);



        /**
         * =====================================================
         * VÉRIFIER QUE L'AFFECTATION EST BIEN UN CP
         * =====================================================
         */

        if ($assignment->position?->code !== 'CP') {

            return back()->withErrors([
                'assignment' => "Cette affectation n'est pas un CP"
            ]);
        }



        /**
         * =====================================================
         * VÉRIFIER SI LA CAMPAGNE
         * POSSÈDE DÉJÀ UN CP ACTIF
         * =====================================================
         */

        $campaignAlreadyUsed = Assignment::where(

            'campaign_id',
            $validated['campaign_id']

        )

            ->where('status', 'actif')

            ->whereHas('position', function ($query) {

                $query->where('code', 'CP');
            })

            ->exists();



        /**
         * Campagne déjà occupée
         */
        if ($campaignAlreadyUsed) {

            return back()->withErrors([
                'campaign' => 'Cette campagne possède déjà un CP'
            ]);
        }



        /**
         * =====================================================
         * EMPÊCHER DOUBLE AFFECTATION
         * =====================================================
         *
         * Le CP ne doit pas avoir
         * deux fois la même campagne
         */

        $alreadyAssigned = Assignment::where(

            'employee_id',
            $assignment->employee_id

        )

            ->where('campaign_id', $validated['campaign_id'])

            ->where('status', 'actif')

            ->exists();



        if ($alreadyAssigned) {

            return back()->withErrors([
                'assignment' => 'Le CP possède déjà cette campagne'
            ]);
        }



        /**
         * =====================================================
         * CRÉATION DE LA NOUVELLE AFFECTATION
         * =====================================================
         */

        DB::transaction(function () use (

            $assignment,
            $validated

        ) {

            /**
             * Nouvelle affectation
             */
            $newAssignment = Assignment::create([

                /**
                 * Même employé
                 */
                'employee_id' => $assignment->employee_id,

                /**
                 * Nouvelle campagne
                 */
                'campaign_id' => $validated['campaign_id'],

                /**
                 * Même position CP
                 */
                'position_id' => $assignment->position_id,

                /**
                 * Pas de manager
                 */
                'manager_id' => null,

                /**
                 * Actif
                 */
                'status' => 'actif',

                /**
                 * Début maintenant
                 */
                'start_date' => now(),
            ]);



            /**
             * =================================================
             * HISTORIQUE
             * =================================================
             */

            AssignmentHistory::create([

                'assignment_id' => $newAssignment->id,

                'employee_id' => $assignment->employee_id,

                'old_manager_id' => null,

                'new_manager_id' => $assignment->employee_id,

                'old_campaign_id' => null,

                'new_campaign_id' => $validated['campaign_id'],

                'action_type' => 'assign',

                'changed_by' => Auth::id(),

                'reason' => 'Nouvelle campagne ajoutée au CP',
            ]);
        });



        /**
         * =====================================================
         * RETOUR
         * =====================================================
         */

        return back()->with(

            'success',
            'Nouvelle campagne affectée avec succès'
        );
    }


    /**
     * =========================================================
     * PAGE AFFECTATION TC
     * =========================================================
     */
    public function assignTC()
    {
        /**
         * =====================================================
         * POSITION TC
         * =====================================================
         */
        $tcPosition = Position::where('code', 'TC')->first();

        /**
         * =====================================================
         * POSITION SUP
         * =====================================================
         */
        $supPosition = Position::where('code', 'SUP')->first();



        /**
         * =====================================================
         * TC DÉJÀ AFFECTÉS
         * =====================================================
         */
        $assignedTCs = Assignment::with([

            'employee.user.role',

            'campaign',

            'manager'

        ])

            ->where('position_id', $tcPosition->id)

            ->where('status', 'actif')

            ->latest()

            ->get();



        /**
         * =====================================================
         * IDS TC AFFECTÉS
         * =====================================================
         */
        $assignedEmployeeIds = Assignment::where(

            'position_id',
            $tcPosition->id

        )

            ->where('status', 'actif')

            ->pluck('employee_id');



        /**
         * =====================================================
         * TC DISPONIBLES
         * =====================================================
         */
        $unassignedTCs = Employee::with([

            'user.role'

        ])

            ->whereHas('user.role', function ($query) {

                $query->where('name', 'tc');
            })

            ->whereNotIn('id', $assignedEmployeeIds)

            ->latest()

            ->get();



        /**
         * =====================================================
         * SUP ACTIFS
         * =====================================================
         */
        $supAssignments = Assignment::with([

            'employee',

            'campaign'

        ])

            ->where('position_id', $supPosition->id)

            ->where('status', 'actif')

            ->latest()

            ->get();



        /**
         * =====================================================
         * RETOUR
         * =====================================================
         */
        return Inertia::render('Assignments/AssignTC', [

            'unassignedTCs' => $unassignedTCs,

            'assignedTCs' => $assignedTCs,

            'supAssignments' => $supAssignments,
        ]);
    }

    /**
     * =========================================================
     * AFFECTER TC À UN SUP
     * =========================================================
     */
    public function storeTC(Request $request)
    {
        /**
         * =====================================================
         * VALIDATION
         * =====================================================
         */
        $validated = $request->validate([

            'employee_id' => 'required|exists:employees,id',

            'sup_assignment_id' => 'required|exists:assignments,id',
        ]);



        /**
         * =====================================================
         * POSITION TC
         * =====================================================
         */
        $tcPosition = Position::where('code', 'TC')->first();



        /**
         * =====================================================
         * EMPLOYÉ
         * =====================================================
         */
        $employee = Employee::with([

            'user.role'

        ])

            ->findOrFail($validated['employee_id']);



        /**
         * =====================================================
         * VÉRIFIER ROLE TC
         * =====================================================
         */
        if ($employee->user?->role?->name !== 'tc') {

            return back()->withErrors([
                'employee' => "Cet employé n'est pas un TC"
            ]);
        }



        /**
         * =====================================================
         * SUP AFFECTATION
         * =====================================================
         */
        $supAssignment = Assignment::findOrFail(

            $validated['sup_assignment_id']
        );



        /**
         * =====================================================
         * EMPÊCHER DOUBLE AFFECTATION
         * =====================================================
         */
        $alreadyAssigned = Assignment::where(

            'employee_id',
            $employee->id

        )

            ->where('status', 'actif')

            ->exists();



        if ($alreadyAssigned) {

            return back()->withErrors([
                'employee' => 'TC déjà affecté'
            ]);
        }



        /**
         * =====================================================
         * TRANSACTION
         * =====================================================
         */
        DB::transaction(function () use (

            $employee,
            $supAssignment,
            $tcPosition

        ) {

            /**
             * =================================================
             * AFFECTATION TC
             * =================================================
             */
            $newAssignment = Assignment::create([

                'employee_id' => $employee->id,

                /**
                 * Hérite campagne SUP
                 */
                'campaign_id' => $supAssignment->campaign_id,

                /**
                 * Position TC
                 */
                'position_id' => $tcPosition->id,

                /**
                 * Manager = SUP
                 */
                'manager_id' => $supAssignment->employee_id,

                'status' => 'actif',

                'start_date' => now(),
            ]);



            /**
             * =================================================
             * HISTORIQUE
             * =================================================
             */
            AssignmentHistory::create([

                'assignment_id' => $newAssignment->id,

                'employee_id' => $employee->id,

                'old_manager_id' => null,

                'new_manager_id' => $supAssignment->employee_id,

                'old_campaign_id' => null,

                'new_campaign_id' => $supAssignment->campaign_id,

                'action_type' => 'assign',

                'changed_by' => Auth::id(),

                'reason' => 'Affectation TC sur superviseur',
            ]);
        });



        /**
         * =====================================================
         * RETOUR
         * =====================================================
         */
        return back()->with(

            'success',
            'TC affecté avec succès'
        );
    }


/**
 * =========================================================
 * LIBÉRATION PRINCIPALE
 * =========================================================
 *
 * Point d'entrée unique.
 *
 * Cette méthode :
 * - détecte le niveau (CP / SUP / TC)
 * - détecte le mode (solo / cascade)
 * - délègue à la bonne méthode
 */
public function release(
    Request $request,
    Assignment $assignment
) {

    /**
     * =====================================================
     * VALIDATION
     * =====================================================
     */
    $validated = $request->validate([

        /**
         * solo
         * cascade
         */
        'mode' => 'required|in:solo,cascade',

        /**
         * remplaçant
         */
        'new_manager_id' => 'nullable|exists:employees,id',

        /**
         * raison
         */
        'reason' => 'nullable|string|max:1000',
    ]);



    /**
     * =====================================================
     * VÉRIFIER SI DÉJÀ TERMINÉ
     * =====================================================
     */
    if ($assignment->status !== 'actif') {

        return back()->withErrors([
            'assignment' => 'Cette affectation est déjà terminée'
        ]);
    }



    /**
     * =====================================================
     * TYPE POSITION
     * =====================================================
     */
    $positionCode = $assignment->position?->code;



    /**
     * =====================================================
     * TRANSACTION
     * =====================================================
     */
    DB::transaction(function () use (

        $assignment,
        $validated,
        $positionCode

    ) {

        /**
         * =================================================
         * CP
         * =================================================
         */
        if ($positionCode === 'CP') {

            $this->releaseCP(
                $assignment,
                $validated
            );
        }



        /**
         * =================================================
         * SUP
         * =================================================
         */
        elseif ($positionCode === 'SUP') {

            $this->releaseSUP(
                $assignment,
                $validated
            );
        }



        /**
         * =================================================
         * TC
         * =================================================
         */
        elseif ($positionCode === 'TC') {

            $this->releaseTC(
                $assignment,
                $validated
            );
        }
    });



    /**
     * =====================================================
     * RETOUR
     * =====================================================
     */
    return back()->with(
        'success',
        'Libération effectuée avec succès'
    );
}



/**
 * =========================================================
 * LIBÉRATION TC
 * =========================================================
 *
 * Le TC n'a pas d'enfants.
 *
 * Donc :
 * - solo = cascade
 */
private function releaseTC(
    Assignment $assignment,
    array $data
) {

    /**
     * =====================================================
     * TERMINER AFFECTATION
     * =====================================================
     */
    $this->terminateAssignment($assignment);



    /**
     * =====================================================
     * HISTORIQUE
     * =====================================================
     */
    $this->createHistory(

        assignment: $assignment,

        actionType: 'release',

        reason:
            $data['reason']
            ??
            'Libération TC',

        oldManagerId: $assignment->manager_id,

        newManagerId: null,

        oldCampaignId: $assignment->campaign_id,

        newCampaignId: null,
    );
}



/**
 * =========================================================
 * LIBÉRATION SUP
 * =========================================================
 */
private function releaseSUP(
    Assignment $assignment,
    array $data
) {

    /**
     * =====================================================
     * TC DU SUP
     * =====================================================
     */
    $tcAssignments = Assignment::where(

        'manager_id',
        $assignment->employee_id

    )

        ->where('status', 'actif')

        ->get();



    /**
     * =====================================================
     * MODE SOLO
     * =====================================================
     *
     * On remplace juste le manager.
     */
    if ($data['mode'] === 'solo') {

        /**
         * Nouveau manager obligatoire
         */
        if (!$data['new_manager_id']) {

            throw new \Exception(
                'Nouveau manager obligatoire'
            );
        }



        /**
         * Réaffectation TC
         */
        foreach ($tcAssignments as $tcAssignment) {

            /**
             * Ancien manager
             */
            $oldManagerId = $tcAssignment->manager_id;



            /**
             * Nouveau manager
             */
            $tcAssignment->update([

                'manager_id' =>
                    $data['new_manager_id']
            ]);



            /**
             * Historique
             */
            $this->createHistory(

                assignment: $tcAssignment,

                actionType: 'reassign',

                reason:
                    $data['reason']
                    ??
                    'Réaffectation TC suite départ SUP',

                oldManagerId: $oldManagerId,

                newManagerId:
                    $data['new_manager_id'],

                oldCampaignId:
                    $tcAssignment->campaign_id,

                newCampaignId:
                    $tcAssignment->campaign_id,
            );
        }
    }



    /**
     * =====================================================
     * MODE CASCADE
     * =====================================================
     *
     * On libère tous les TC.
     */
    else {

        foreach ($tcAssignments as $tcAssignment) {

            $this->releaseTC(
                $tcAssignment,
                $data
            );
        }
    }



    /**
     * =====================================================
     * TERMINER SUP
     * =====================================================
     */
    $this->terminateAssignment($assignment);



    /**
     * =====================================================
     * HISTORIQUE SUP
     * =====================================================
     */
    $this->createHistory(

        assignment: $assignment,

        actionType: 'release',

        reason:
            $data['reason']
            ??
            'Libération SUP',

        oldManagerId:
            $assignment->manager_id,

        newManagerId: null,

        oldCampaignId:
            $assignment->campaign_id,

        newCampaignId: null,
    );
}



/**
 * =========================================================
 * LIBÉRATION CP
 * =========================================================
 */
private function releaseCP(
    Assignment $assignment,
    array $data
) {

    /**
     * =====================================================
     * SUP DU CP
     * =====================================================
     */
    $supAssignments = Assignment::where(

        'manager_id',
        $assignment->employee_id

    )

        ->where('status', 'actif')

        ->get();



    /**
     * =====================================================
     * MODE SOLO
     * =====================================================
     *
     * On change juste le manager des SUP.
     */
    if ($data['mode'] === 'solo') {

        /**
         * Remplaçant obligatoire
         */
        if (!$data['new_manager_id']) {

            throw new \Exception(
                'Nouveau CP obligatoire'
            );
        }



        /**
         * Réaffectation SUP
         */
        foreach ($supAssignments as $supAssignment) {

            $oldManagerId =
                $supAssignment->manager_id;



            /**
             * Nouveau CP
             */
            $supAssignment->update([

                'manager_id' =>
                    $data['new_manager_id']
            ]);



            /**
             * Historique
             */
            $this->createHistory(

                assignment: $supAssignment,

                actionType: 'reassign',

                reason:
                    $data['reason']
                    ??
                    'Réaffectation SUP suite départ CP',

                oldManagerId:
                    $oldManagerId,

                newManagerId:
                    $data['new_manager_id'],

                oldCampaignId:
                    $supAssignment->campaign_id,

                newCampaignId:
                    $supAssignment->campaign_id,
            );
        }
    }



    /**
     * =====================================================
     * MODE CASCADE
     * =====================================================
     */
    else {

        /**
         * Libération SUP + TC
         */
        foreach ($supAssignments as $supAssignment) {

            $this->releaseSUP(
                $supAssignment,
                [
                    ...$data,
                    'mode' => 'cascade'
                ]
            );
        }
    }



    /**
     * =====================================================
     * TERMINER CP
     * =====================================================
     */
    $this->terminateAssignment($assignment);



    /**
     * =====================================================
     * HISTORIQUE CP
     * =====================================================
     */
    $this->createHistory(

        assignment: $assignment,

        actionType: 'release',

        reason:
            $data['reason']
            ??
            'Libération CP',

        oldManagerId: null,

        newManagerId: null,

        oldCampaignId:
            $assignment->campaign_id,

        newCampaignId: null,
    );
}



/**
 * =========================================================
 * TERMINER AFFECTATION
 * =========================================================
 *
 * Méthode factorisée.
 */
private function terminateAssignment(
    Assignment $assignment
) {

    $assignment->update([

        'status' => 'termine',

        'end_date' => now(),
    ]);
}



/**
 * =========================================================
 * HISTORIQUE FACTORISÉ
 * =========================================================
 */
private function createHistory(

    Assignment $assignment,

    string $actionType,

    ?string $reason,

    ?int $oldManagerId,

    ?int $newManagerId,

    ?int $oldCampaignId,

    ?int $newCampaignId

) {

    AssignmentHistory::create([

        'assignment_id' => $assignment->id,

        'employee_id' => $assignment->employee_id,

        'old_manager_id' => $oldManagerId,

        'new_manager_id' => $newManagerId,

        'old_campaign_id' => $oldCampaignId,

        'new_campaign_id' => $newCampaignId,

        'action_type' => $actionType,

        'changed_by' => Auth::id(),

        'reason' => $reason,
    ]);
}
}
