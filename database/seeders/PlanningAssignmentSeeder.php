<?php

// database/seeders/PlanningAssignmentSeeder.php
namespace Database\Seeders;

use App\Models\PlanningAssignment;
use App\Models\Employee;
use App\Models\PlanningModel;
use App\Models\Assignment;
use Illuminate\Database\Seeder;

class PlanningAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $model35h = PlanningModel::where('name', 'like', '%35h%')->first();
        $sups = Employee::whereHas('position', fn($q) => $q->where('code', 'SUP'))->get();
        $validator = Employee::whereHas('position', fn($q) => $q->where('code', 'CP'))->first();

        if (!$model35h || $sups->isEmpty()) {
            return;
        }

        // 1. Assigner le planning 35h aux superviseurs d'abord
        foreach ($sups as $sup) {
            // Vérifier si le superviseur a une affectation active
            $hasActiveAssignment = Assignment::where('employee_id', $sup->id)
                ->where('status', 'actif')
                ->exists();

            if ($hasActiveAssignment) {
                PlanningAssignment::create([
                    'planning_model_id' => $model35h->id,
                    'employee_id'       => $sup->id,
                    'start_date'        => now()->startOfMonth(),
                    'end_date'          => now()->addMonths(6),
                    'status'            => 'en attente',
                    'created_by'        => $validator->id,
                ]);
            }
        }

        // 2. Assigner le planning aux TC uniquement si leur superviseur a un planning
        $supervisorIdsWithPlanning = PlanningAssignment::where('status', 'en attente')
            ->whereHas('employee.position', fn($q) => $q->where('code', 'SUP'))
            ->pluck('employee_id');

        foreach ($supervisorIdsWithPlanning as $supId) {
            // Récupérer les TC sous ce superviseur
            $tcIds = Assignment::where('manager_id', $supId)
                ->where('status', 'actif')
                ->pluck('employee_id');

            foreach ($tcIds as $tcId) {
                // Vérifier si le TC n'a pas déjà un planning
                $existingPlanning = PlanningAssignment::where('employee_id', $tcId)
                    ->where(function ($query) {
                        $query->whereBetween('start_date', [now()->startOfMonth(), now()->addMonths(6)])
                              ->orWhereBetween('end_date', [now()->startOfMonth(), now()->addMonths(6)]);
                    })
                    ->exists();

                if (!$existingPlanning) {
                    PlanningAssignment::create([
                        'planning_model_id' => $model35h->id,
                        'employee_id'       => $tcId,
                        'start_date'        => now()->startOfMonth(),
                        'end_date'          => now()->addMonths(6),
                        'status'            => 'en attente',
                        'created_by'        => $validator->id,
                    ]);
                }
            }
        }
    }
}