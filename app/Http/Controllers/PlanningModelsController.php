<?php

namespace App\Http\Controllers;

use App\Models\PlanningAssignment;
use App\Models\PlanningModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class PlanningModelsController extends Controller
{
    /**
     * Affiche la liste des modèles de planning prédéfinis.
     * Les modèles définissent les heures travaillées par jour de la semaine.
     */
    public function index()
    {
        return Inertia::render('Planning/Models/Index', [
            // Récupération des modèles avec le nom du créateur et le nombre d'affectations
            'planningModels' => PlanningModel::with('creator:id,name')
                ->withCount('assignments')
                ->latest()
                ->get(),

            // Liste des plannings actuellement en cours de validité
            'activeAssignments' => PlanningAssignment::with(['employee.user', 'planningModel'])
                ->where('status', 'validé')
                ->latest()
                ->get()
                ->map(fn($assign) => [
                    'id' => $assign->id,
                    'user_name' => $assign->employee?->user?->name ?? 'N/A',
                    'model_name' => $assign->planningModel?->name ?? 'N/A',
                    'start_date' => $assign->start_date ? $assign->start_date->format('Y-m-d') : 'N/A',
                    'end_date' => $assign->end_date ? $assign->end_date->format('Y-m-d') : 'N/A',
                ]),
        ]);
    }

    /**
     * Enregistre un nouveau modèle de planning hebdomadaire.
     * Calcule automatiquement le total des heures sur la semaine.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monday_hours' => 'required|numeric|min:0|max:24',
            'tuesday_hours' => 'required|numeric|min:0|max:24',
            'wednesday_hours' => 'required|numeric|min:0|max:24',
            'thursday_hours' => 'required|numeric|min:0|max:24',
            'friday_hours' => 'required|numeric|min:0|max:24',
            'saturday_hours' => 'required|numeric|min:0|max:24',
            'sunday_hours' => 'required|numeric|min:0|max:24',
            'total_hours' => 'required|numeric|min:0',
        ]);

        $validated['created_by'] = Auth::id();

        PlanningModel::create($validated);

        return redirect()->back()->with('success', 'Modèle créé avec succès.');
    }

    /**
     * Met à jour un modèle existant.
     */
    public function update(Request $request, PlanningModel $model)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monday_hours' => 'required|numeric|min:0|max:24',
            'tuesday_hours' => 'required|numeric|min:0|max:24',
            'wednesday_hours' => 'required|numeric|min:0|max:24',
            'thursday_hours' => 'required|numeric|min:0|max:24',
            'friday_hours' => 'required|numeric|min:0|max:24',
            'saturday_hours' => 'required|numeric|min:0|max:24',
            'sunday_hours' => 'required|numeric|min:0|max:24',
            'total_hours' => 'required|numeric|min:0',
        ]);

        $model->update($validated);

        return redirect()->back()->with('success', 'Modèle mis à jour avec succès.');
    }

    /**
     * Supprime un modèle.
     */
    public function destroy(PlanningModel $model)
    {
        if ($model->assignments()->exists()) {
            return redirect()->back()->with('error', 'Ce modèle est utilisé par des employés.');
        }

        $model->delete();
        return redirect()->back()->with('success', 'Modèle supprimé.');
    }
}
