<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimesheetEntryRequest;
use App\Http\Requests\UpdateTimesheetEntryRequest;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\PlanningAssignment;
use Carbon\Carbon;
use Inertia\Inertia;

class TimesheetEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $entries = TimesheetEntry::with(['timesheet'])->get();
        return Inertia::render('Timesheets/TimesCard', [
            'entries' => $entries
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTimesheetEntryRequest $request)
    {
        $validated = $request->validated();
        $timesheetIds = $request->input('timesheet_ids', [$validated['timesheet_id']]);

        foreach ($timesheetIds as $tsId) {
            // Récupération dynamique des heures prévues (planned_hours)
            $date = Carbon::parse($validated['date']);
            $dayName = strtolower($date->format('l')); // e.g., 'monday'
            $columnName = $dayName . '_hours'; // e.g., 'monday_hours'

            // On cherche l'employé via le timesheet
            $timesheet = Timesheet::findOrFail($tsId);

            // On cherche le planning actif pour cet employé à cette date
            $assignment = PlanningAssignment::where('employee_id', $timesheet->employee_id)
                ->where('start_date', '<=', $validated['date'])
                ->where(function ($q) use ($validated) {
                    $q->where('end_date', '>=', $validated['date'])
                        ->orWhereNull('end_date');
                })
                ->where('status', 'validé')
                ->with('planningModel')
                ->first();

            // Si on trouve un planning, on prend les heures prévues, sinon 0
            $plannedHours = $assignment ? (float)$assignment->planningModel->$columnName : 0.0;

            // Calcul de la durée de travail (total_hours)
            $totalHours = 0.0;
            $overtimeHours = 0.0;

            if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
                $start = Carbon::parse($validated['check_in']);
                $end = Carbon::parse($validated['check_out']);
                $diffInMinutes = $start->diffInMinutes($end);

                // Durée = (Sortie - Arrivée - Pause) / 60
                $workMinutes = $diffInMinutes - (int)($validated['break_duration'] ?? 0);
                $totalHours = max(0, $workMinutes / 60);

                // Calcul des heures supplémentaires (écarts)
                $overtimeHours = $totalHours - $plannedHours;
            }

            $entry = TimesheetEntry::updateOrCreate(
                [
                    'timesheet_id' => $tsId,
                    'date' => $validated['date'],
                ],
                [
                    'check_in' => $validated['check_in'],
                    'check_out' => $validated['check_out'],
                    'break_duration' => $validated['break_duration'] ?? 0,
                    'total_hours' => $totalHours,
                    'planned_hours' => $plannedHours,
                    'overtime_hours' => $overtimeHours,
                    'comment' => $validated['comment'] ?? null,
                ]
            );

            // Mettre à jour le statut de la feuille de temps parente
            if ($timesheet->status === 'brouillon') {
                $timesheet->update(['status' => 'valide']);
            }
        }

        return redirect()->back()->with('success', 'Entrée(s) enregistrée(s) avec succès.');
    }

    /**
     * RÉCUPÉRATION AJAX D'UNE ENTRÉE
     * Utile pour charger les données d'un jour précis pour un employé.
     */
    public function show($employeeId, $date)
    {
        $entry = TimesheetEntry::whereHas('timesheet', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId);
        })
            ->where('date', $date)
            ->first();

        return response()->json($entry);
    }

    /**
     * SUPPRESSION D'UNE ENTRÉE
     * Permet de vider une case du calendrier.
     */
    public function destroy(TimesheetEntry $entry)
    {
        $timesheet = $entry->timesheet;

        // On ne peut supprimer que si la semaine n'est pas verrouillée
        if ($timesheet->status !== 'soumis') {
            $entry->delete();
        }

        return back();
    }
}
