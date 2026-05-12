<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Les données de référence (Tables sans dépendances)
        $this->call([
            RoleSeeder::class,       // admin, cp, sup, tc
            PositionSeeder::class,   // RH, TC, SUP, CP
            CampaignSeeder::class,   // Vos campagnes de production
        ]);

        // 2. Création de l'utilisateur de test (Administrateur)
        // On s'assure qu'il a le rôle 'admin'
        $adminRole = Role::where('name', 'admin')->first();

        $adminUser = User::where('email', 'admin@example.com')->first();
        
        if (!$adminUser) {
            $adminUser = User::factory()->create([
                'email' => 'admin@example.com',
                'role_id' => $adminRole->id,
            ]);
        }

        // 3. Les données dépendantes
        $this->call([
            EmployeeSeeder::class,            // Crée les employés liés aux users
            AssignmentSeeder::class,          // Lie employés, campagnes et managers
            PlanningModelSeeder::class,       // Modèles horaires (35h, etc.)
            PlanningAssignmentSeeder::class,  // Affectation des plannings
            TimesheetSeeder::class,           // Enveloppes de feuilles de temps
            TimesheetEntrySeeder::class,      // Détail des pointages
            LogHistorySeeder::class,          // Historiques et Logs d'activité
        ]);
    }
}