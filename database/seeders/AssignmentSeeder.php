<?php

// database/seeders/AssignmentSeeder.php
namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Campaign;
use App\Models\Position;
use App\Models\AssignmentHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::where('status', 'active')->get();
        
        $posCp = Position::where('code', 'CP')->first();
        $posSup = Position::where('code', 'SUP')->first();
        $posTc = Position::where('code', 'TC')->first();

        // 1. Récupérer les employés par position
        $cps = Employee::where('position_id', $posCp->id)->get();
        $sups = Employee::where('position_id', $posSup->id)->get();
        $tcs = Employee::where('position_id', $posTc->id)->get();

        if ($campaigns->isEmpty() || $cps->isEmpty() || $sups->isEmpty() || $tcs->isEmpty()) {
            $this->command->error("Données insuffisantes pour les affectations (Campagnes, CP, SUP ou TC manquants) !");
            return;
        }

        DB::transaction(function () use ($campaigns, $cps, $sups, $tcs, $posCp, $posSup, $posTc) {
            
            // --- ÉTAPE 1 : Affecter les CP aux campagnes ---
            // On s'assure que chaque campagne a un CP
            foreach ($campaigns as $index => $campaign) {
                $cp = $cps[$index % $cps->count()];
                
                $assignment = Assignment::create([
                    'employee_id' => $cp->id,
                    'campaign_id' => $campaign->id,
                    'position_id' => $posCp->id,
                    'manager_id'  => null,
                    'status'      => 'actif',
                    'start_date'  => now()->subMonths(3),
                ]);

                $this->createHistory($assignment);
            }

            // --- ÉTAPE 2 : Affecter les SUP aux CP ---
            // Chaque SUP est rattaché à un CP (et donc à la campagne du CP)
            $cpAssignments = Assignment::where('position_id', $posCp->id)->where('status', 'actif')->get();
            
            foreach ($sups as $index => $sup) {
                $cpAssign = $cpAssignments->random();
                
                $assignment = Assignment::create([
                    'employee_id' => $sup->id,
                    'campaign_id' => $cpAssign->campaign_id,
                    'manager_id'  => $cpAssign->employee_id,
                    'position_id' => $posSup->id,
                    'status'      => 'actif',
                    'start_date'  => now()->subMonths(2),
                ]);

                $this->createHistory($assignment);
            }

            // --- ÉTAPE 3 : Affecter les TC aux SUP ---
            // Chaque TC est rattaché à un SUP (et donc à la campagne du SUP)
            $supAssignments = Assignment::where('position_id', $posSup->id)->where('status', 'actif')->get();

            foreach ($tcs as $index => $tc) {
                // On n'affecte que 80% des TC pour en laisser des "disponibles" pour les tests
                if ($index > ($tcs->count() * 0.8)) continue;

                $supAssign = $supAssignments->random();
                
                $assignment = Assignment::create([
                    'employee_id' => $tc->id,
                    'campaign_id' => $supAssign->campaign_id,
                    'manager_id'  => $supAssign->employee_id,
                    'position_id' => $posTc->id,
                    'status'      => 'actif',
                    'start_date'  => now()->subMonths(1),
                ]);

                $this->createHistory($assignment);
            }
        });
    }

    private function createHistory($assignment)
    {
        AssignmentHistory::create([
            'assignment_id'   => $assignment->id,
            'employee_id'     => $assignment->employee_id,
            'new_manager_id'  => $assignment->manager_id,
            'new_campaign_id' => $assignment->campaign_id,
            'action_type'     => 'assign',
            'changed_by'      => 1, // Admin par défaut
            'reason'          => 'Initial seeding',
        ]);
    }
}
