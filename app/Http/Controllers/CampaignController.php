<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CampaignController extends Controller
{
    /**
     * Liste des campagnes avec compteurs d'affectations
     */
    public function index()
    {
        // On récupère les campagnes avec le nombre d'affectations actives pour chaque campagne
        $campaigns = Campaign::withCount(['assignments' => function ($query) {
            $query->where('status', 'actif');
        }])->latest()->get();

        // Retourne la vue de la liste des campagnes (Image 3)
        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

     /**
     * Campagnes actives
     */
    public function active()
    {
        $campaigns = Campaign::withCount([
            'assignments' => function ($query) {
                $query->where('status', 'actif');
            }
        ])
        ->where('status', 'active')
        ->latest()
        ->get();

        return Inertia::render('Campaigns/ActiveCampaign', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Campagnes inactives + terminées
     */
    public function inactive()
    {
        $campaigns = Campaign::withCount([
            'assignments' => function ($query) {
                $query->where('status', 'actif');
            }
        ])
        ->whereIn('status', ['inactive'])
        ->latest()
        ->get();

        return Inertia::render('Campaigns/InactiveCampaign', [
            'campaigns' => $campaigns,
        ]);
    }


    /**
     * Formulaire de création (non utilisé avec le modal PrimeVue)
     */
    public function create()
    {
        //
    }

    /**
     * Enregistrer une nouvelle campagne
     */
    public function store(Request $request)
    {
        // Validation des données entrantes
        $validated = $request->validate([
            'name' => 'required|string|max:255', // Nom obligatoire
            'description' => 'nullable|string', // Description optionnelle
            'start_date' => 'required|date', // Date de début obligatoire
            'end_date' => 'nullable|date|after_or_equal:start_date', // Date de fin doit être après ou égale au début
            'status' => 'required|in:active,inactive,terminee', // Statut limité aux valeurs définies
        ]);

        // Création de la campagne
        $campaign = Campaign::create($validated);

        // Enregistrement de l'action dans l'historique (ActivityLog)
        ActivityLog::create([
            'user_id' => Auth::id(), // ID de l'utilisateur qui crée
            'action' => 'create', // Type d'action
            'model_type' => Campaign::class, // Modèle concerné
            'model_id' => $campaign->id, // ID de la campagne créée
            'description' => "Création de la campagne : {$campaign->name}", // Description détaillée
            'ip_address' => $request->ip(), // Adresse IP de l'utilisateur
        ]);

        // Redirection vers la page de détail pour commencer les affectations
        return redirect()->route('campaigns.show', $campaign->id)->with('success', 'Campagne créée avec succès. Vous pouvez maintenant affecter des ressources.');
    }

    /**
     * Affiche le détail d'une campagne avec sa hiérarchie (Image 1)
     */
    public function show(Campaign $campaign)
    {
        // On charge les affectations avec les relations pour construire la vue hiérarchique
        $campaign->load(['assignments' => function ($query) {
            $query->where('status', 'actif')->with(['employee', 'manager', 'position']);
        }]);

        // On récupère aussi l'historique spécifique à cette campagne
        $history = \App\Models\AssignmentHistory::where('old_campaign_id', $campaign->id)
            ->orWhere('new_campaign_id', $campaign->id)
            ->with(['employee', 'author'])
            ->latest()
            ->get();

        // Employés disponibles (non affectés à une campagne active)
        $availableEmployees = \App\Models\Employee::where('status', 'actif')
            ->whereDoesntHave('assignments', function ($q) {
                $q->where('status', 'actif');
            })
            ->with('position')
            ->get();

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
            'assignments' => $campaign->assignments,
            'history' => $history,
            'availableEmployees' => $availableEmployees,
            'positions' => \App\Models\Position::all()
        ]);
    }

    /**
     * Formulaire d'édition (non utilisé avec le modal PrimeVue)
     */
    public function edit(Campaign $campaign)
    {
        //
    }

    /**
     * Mettre à jour une campagne
     */
    public function update(Request $request, Campaign $campaign)
    {
        // Validation des données de mise à jour
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,terminee',
        ]);

        // Mise à jour de la campagne
        $campaign->update($validated);

        // Enregistrement de la modification dans l'historique
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'model_type' => Campaign::class,
            'model_id' => $campaign->id,
            'description' => "Mise à jour de la campagne : {$campaign->name}",
            'ip_address' => $request->ip(),
        ]);

        // Retour à la page précédente
        return redirect()->back();
    }

    /**
     * Changer uniquement le statut d'une campagne
     */
    public function changeStatus(Request $request, Campaign $campaign)
    {

        // Validation du nouveau statut
        $validated = $request->validate([
            'status' => 'required|in:active,inactive', // Uniquement actif/inactif via ce bouton
        ]);

        // Sauvegarde de l'ancien statut pour la description
        $oldStatus = $campaign->status;

        // Mise à jour du statut
        $campaign->update(['status' => $validated['status']]);

        // Tracé spécifique pour le changement de statut (Historique)
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'status_change',
            'model_type' => Campaign::class,
            'model_id' => $campaign->id,
            'description' => "Changement de statut de la campagne {$campaign->name} : {$oldStatus} -> {$validated['status']}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back();
    }

    /**
     * Supprimer une campagne (En réalité, on la clôture/termine)
     */
    public function destroy(Request $request, Campaign $campaign)
    {
        // On garde l'ancien statut
        $oldStatus = $campaign->status;

        DB::transaction(function () use ($campaign, $oldStatus, $request) {
            // 1. On change le statut en 'terminee'
            $campaign->update(['status' => 'terminee']);

            // 2. On libère automatiquement toutes les ressources de cette campagne
            $activeAssignments = \App\Models\Assignment::where('campaign_id', $campaign->id)
                ->where('status', 'actif')
                ->with(['employee', 'position'])
                ->get();

            foreach ($activeAssignments as $assignment) {
                // Terminer l'affectation
                $assignment->update([
                    'status' => 'termine',
                    'end_date' => now(),
                ]);

                // Créer une trace dans l'historique
                \App\Models\AssignmentHistory::create([
                    'assignment_id'   => $assignment->id,
                    'employee_id'     => $assignment->employee_id,
                    'old_manager_id'  => $assignment->manager_id,
                    'old_campaign_id' => $assignment->campaign_id,
                    'action_type'     => 'release',
                    'changed_by'      => Auth::id(),
                    'reason'          => "Libération automatique suite à la clôture de la campagne : {$campaign->name}",
                ]);
            }

            // 3. Enregistrement de la clôture dans le journal d'activité
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'cloture_campagne',
                'model_type' => Campaign::class,
                'model_id' => $campaign->id,
                'description' => "Campagne clôturée : {$campaign->name}. Toutes les ressources ont été libérées automatiquement.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->back()->with('success', 'Campagne clôturée et ressources libérées.');
    }
}
