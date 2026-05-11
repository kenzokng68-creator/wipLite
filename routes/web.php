<?php

use App\Http\Controllers\{
    EmployeeController,
    PositionController,
    ActivityLogController,
    PlanningModelsController,
    PlanningAssignmentController,
    ProfileController,
    TimesheetController,
    TimesheetEntryController,
    ReportingController,
    RoleController,
    UserController,
    AssignmentController,
    CampaignController,
    NotificationController
};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1. REDIRECTION INITIALE
Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role?->name;
        return redirect()->route(match ($role) {
            'admin' => 'dashboard.admin',
            'cp'    => 'dashboard.cp',
            'sup'   => 'dashboard.sup',
            'tc'    => 'dashboard.tc',
            default => 'dashboard.tc',
        });
    }
    return redirect()->route('login');
});

// 2. ROUTES AUTHENTIFIÉES
Route::middleware(['auth', 'verified'])->group(function () {

    // --- DASHBOARDS ---
    Route::get('/dashboard', function () {
        $role = auth()->user()->role?->name;
        return redirect()->route(match ($role) {
            'admin' => 'dashboard.admin',
            'cp'    => 'dashboard.cp',
            'sup'   => 'dashboard.sup',
            'tc'    => 'dashboard.tc',
            default => 'dashboard.tc',
        });
    })->name('dashboard');

    Route::get('/dashboard/admin', [ReportingController::class, 'admin'])->middleware('role:admin')->name('dashboard.admin');
    Route::get('/dashboard/cp', [ReportingController::class, 'chefPlateau'])->middleware('role:cp,admin')->name('dashboard.cp');
    Route::get('/dashboard/sup', [ReportingController::class, 'superviseur'])->middleware('role:sup,admin')->name('dashboard.sup');
    Route::get('/dashboard/tc', [ReportingController::class, 'teleConseiller'])->middleware('role:tc,admin')->name('dashboard.tc');

    // --- EXPORTS & RAPPORTS ---
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/export/excel', [ReportingController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf', [ReportingController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/hr', [ReportingController::class, 'hrReport'])->name('hr');
        Route::get('/campaigns', [ReportingController::class, 'campaignsReport'])->name('campaigns');
        Route::get('/assignments', [ReportingController::class, 'assignmentsReport'])->name('assignments');
        Route::get('/timesheets', [ReportingController::class, 'timesheetsReport'])->name('timesheets');
        Route::get('/team', [ReportingController::class, 'teamReport'])->name('team');
        Route::get('/productivity', [ReportingController::class, 'productivityReport'])->name('productivity');
    });

    // --- PLANNINGS (Accès Restreint CP/ADMIN) ---
    Route::middleware('role:cp,admin')->prefix('planning')->name('planning.')->group(function () {
        Route::resource('models', PlanningModelsController::class)->except(['create', 'show', 'edit']);

        Route::get('/assignments', [PlanningAssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/create', [PlanningAssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/assignments', [PlanningAssignmentController::class, 'store'])->name('assignments.store');
        Route::delete('/assignments/{id}', [PlanningAssignmentController::class, 'destroy'])->name('assignments.destroy');

        Route::post('/assignments/{id}/validate', [PlanningAssignmentController::class, 'validateAssignment'])->name('assignments.validate');
        Route::post('/assignments/bulk-validate', [PlanningAssignmentController::class, 'bulkValidate'])->name('assignments.bulk-validate');
        Route::post('/assignments/validate-all', [PlanningAssignmentController::class, 'validateAll'])->name('assignments.validate-all');
        Route::post('/assignments/{id}/suspend', [PlanningAssignmentController::class, 'suspendAssignment'])->name('assignments.suspend');
        Route::post('/assignments/{id}/terminate', [PlanningAssignmentController::class, 'terminateAssignment'])->name('assignments.terminate');
        Route::get('/validate', [PlanningAssignmentController::class, 'validation'])->name('validate');
    });

    // Planning (Accès général)
    Route::get('/planning/history', [PlanningAssignmentController::class, 'history'])->name('planning.history');
    Route::get('/planning/mine', [PlanningAssignmentController::class, 'mine'])->name('planning.mine');
    Route::get('/planning/team', [PlanningAssignmentController::class, 'team'])->name('planning.team');

    // --- AFFECTATIONS (ASSIGNMENTS) - Routes de feature-ok ---
    Route::prefix('assignments')->name('assignments.')->group(function () {
        Route::get('/', [AssignmentController::class, 'index'])->name('index');
        Route::post('/', [AssignmentController::class, 'store'])->name('store');
        Route::get('/hierarchy', [AssignmentController::class, 'hierarchy'])->name('hierarchy');
        Route::get('/tree', [AssignmentController::class, 'hierarchy'])->name('tree');
        Route::get('/history', [AssignmentController::class, 'history'])->name('history');

        // Actions spécifiques
        Route::post('/{assignment}/release', [AssignmentController::class, 'release'])->name('release');
        Route::post('/{assignment}/reassign', [AssignmentController::class, 'reassign'])->name('reassign');
        Route::post('/assign-campaign/{assignment}', [AssignmentController::class, 'assignNewCampaign'])->name('assignCampaign');
    });

    // Routes d'affectation simplifiées (pour correspondre au front-end de feature-ok)
    Route::get('/assign/cp', [AssignmentController::class, 'assignCP'])->name('assign.cp');
    Route::post('/assign/cp', [AssignmentController::class, 'storeCP'])->name('assign.cp.store');
    Route::get('/assign/sup', [AssignmentController::class, 'assignSUP'])->name('assign.sup');
    Route::post('/assign/sup', [AssignmentController::class, 'storeSUP'])->name('assign.sup.store');
    Route::get('/assign/tc', [AssignmentController::class, 'assignTC'])->name('assign.tc');
    Route::post('/assign/tc', [AssignmentController::class, 'storeTC'])->name('assign.tc.store');

    // --- EMPLOYÉS ---
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/history', [EmployeeController::class, 'history'])->name('history');
        Route::get('/assigned', [EmployeeController::class, 'index'])->name('assigned');
        Route::get('/unassigned', [EmployeeController::class, 'index'])->name('unassigned');
        Route::get('/inactifs', [EmployeeController::class, 'index'])->name('inactifs');
    });
    Route::resource('employees', EmployeeController::class);
    Route::get('/supervisors', [EmployeeController::class, 'index'])->name('supervisors.index');
    Route::get('/teleconseillers', [EmployeeController::class, 'index'])->name('teleconseillers.index');

    // --- CAMPAGNES (Routes de feature-ok) ---
    Route::get('/active/campaigns', [CampaignController::class, 'active'])->name('active');
    Route::get('/inactive/campaigns', [CampaignController::class, 'inactive'])->name('inactive');
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::patch('/{campaign}/status', [CampaignController::class, 'changeStatus'])->name('status.update');
    });
    Route::resource('campaigns', CampaignController::class);

    // --- FEUILLES DE TEMPS (TIMESHEETS) ---
    Route::get('/timesheets', [TimesheetController::class, 'index'])->name('calendar.index');
    Route::prefix('timesheets')->name('timesheets.')->group(function () {
        Route::get('/validate', [TimesheetController::class, 'index'])->name('validate');
        Route::get('/history', [TimesheetController::class, 'index'])->name('history');
        Route::get('/gaps', [TimesheetController::class, 'index'])->name('gaps');
        Route::get('/overtime', [TimesheetController::class, 'index'])->name('overtime');
        Route::get('/{timesheet}', [TimesheetController::class, 'show'])->name('show');
        Route::post('/{timesheet}/submit', [TimesheetController::class, 'submit'])->name('submit');
    });
    Route::post('/timesheet-entries', [TimesheetEntryController::class, 'store'])->name('timesheet-entries.store');

    // --- PROFIL & NOTIFICATIONS ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('notifications', NotificationController::class);
    Route::resource('positions', PositionController::class)->only(['index', 'show']);

    // --- ADMINISTRATION (ADMIN SEULEMENT) ---
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', UserController::class);

        // Gestion des rôles
        Route::get('/users/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/users/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/users/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/users/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // Logs & Stats
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/admin/stats', [ReportingController::class, 'generalStats'])->name('dashboard.admin.stats');
        Route::get('/admin/alerts', [ReportingController::class, 'alerts'])->name('dashboard.admin.alerts');
    });
});

require __DIR__ . '/auth.php';
