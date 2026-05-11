<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Employee;
use App\Models\Assignment;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD ADMIN
    |--------------------------------------------------------------------------
    */

    public function admin()
    {
        return Inertia::render('Dashboard/Admin', [
            'stats' => [
                'employees' => Employee::count(),
                'activeEmployees' => Employee::where('status', 'actif')->count(),
                'campaigns' => Campaign::count(),
                'assignments' => Assignment::where('status', 'actif')->count(),
                'workedHours' => TimesheetEntry::sum('total_hours'),
                'overtimeHours' => TimesheetEntry::sum('overtime_hours'),

                'pendingTimesheets' => Timesheet::where('status', 'soumis')->count(),

            ],

            'campaignStats' => Campaign::withCount(['assignments' => function($query) {
                $query->where('assignments.status', 'actif');
            }])
            ->latest()
            ->take(5)
            ->get(),

            'employeeStats' => Employee::withSum('timesheetEntries', 'total_hours')
                ->withSum('timesheetEntries', 'planned_hours')
                ->with('position')
                ->latest()
                ->take(5)
                ->get(),

            'planningGaps' => [
                'planned' => (float) TimesheetEntry::sum('planned_hours') ?: 1, // Avoid division by zero
                'worked' => (float) TimesheetEntry::sum('total_hours'),
                'gap' => TimesheetEntry::select(
                    DB::raw('SUM(total_hours - planned_hours) as total_gap')
                )->value('total_gap') ?: 0,
            ],

            'monthlyEvolution' => TimesheetEntry::select(
                DB::raw('DATE_FORMAT(date, "%Y-%m") as month'),
                DB::raw('SUM(total_hours) as total_worked'),
                DB::raw('SUM(planned_hours) as total_planned')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->take(6)
            ->get()
        ]);
    }

    /**
     * Export des données en Excel
     */
    public function exportExcel()
    {
        return Excel::download(new EmployeesExport, 'rapport_employes_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export des données en PDF
     */
    public function exportPdf()
    {
        $data = [
            'stats' => [
                'employees' => Employee::count(),
                'active' => Employee::where('status', 'actif')->count(),
                'campaigns' => Campaign::count(),
                'workedHours' => TimesheetEntry::sum('total_hours'),
            ],
            'campaigns' => Campaign::withCount('assignments')->get(),
            'date' => now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('reports.dashboard_pdf', $data);
        return $pdf->download('rapport_decisionnel_' . now()->format('Y-m-d') . '.pdf');
    }

    public function hrReport()
    {
        return Inertia::render('Reports/Hr', [
            'stats' => [
                'total' => Employee::count(),
                'active' => Employee::where('status', 'actif')->count(),
                'inactive' => Employee::where('status', 'inactif')->count(),
                'suspended' => Employee::where('status', 'suspendu')->count(),
            ],
            'employees' => Employee::with('position')->latest()->get()
        ]);
    }

    public function campaignsReport()
    {
        return Inertia::render('Reports/Campaigns', [
            'campaigns' => Campaign::withCount(['assignments' => function($q) {
                $q->where('status', 'actif');
            }])->get()
        ]);
    }

    public function assignmentsReport()
    {
        return Inertia::render('Reports/Assignments', [
            'assignments' => Assignment::with(['employee', 'campaign', 'manager', 'position'])
                ->where('status', 'actif')
                ->latest()
                ->get()
        ]);
    }

    public function timesheetsReport()
    {
        return Inertia::render('Reports/Timesheets', [
            'stats' => [
                'totalWorked' => TimesheetEntry::sum('total_hours'),
                'totalPlanned' => TimesheetEntry::sum('planned_hours'),
            ],
            'entries' => TimesheetEntry::with(['timesheet.employee', 'timesheet.validator'])->latest()->take(100)->get()
        ]);
    }

    public function teamReport()
    {
        $manager = auth()->user()->employee;
        return Inertia::render('Reports/Team', [
            'team' => Employee::whereIn('id', function($query) use ($manager) {
                $query->select('employee_id')->from('assignments')->where('manager_id', $manager?->id)->where('status', 'actif');
            })->with('position')->get()
        ]);
    }

    public function productivityReport()
    {
        return Inertia::render('Reports/Productivity', [
            'stats' => [
                'globalEfficiency' => TimesheetEntry::where('planned_hours', '>', 0)
                    ->select(DB::raw('AVG(total_hours / planned_hours) * 100 as avg_eff'))
                    ->value('avg_eff') ?: 0
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD CHEF DE PLATEAU
    |--------------------------------------------------------------------------
    */

    public function chefPlateau()
    {
        return Inertia::render('Dashboard/ChefPlateau', [
            'stats' => [
                'totalCampaigns' => Campaign::count(),
                'activeAgents' => Employee::where('status', 'actif')->count(),
                'pendingPlannings' => Timesheet::where('status', 'soumis')->count(),
                'workedHoursWeek' => (float) TimesheetEntry::where('date', '>=', now()->startOfWeek())->sum('total_hours'),
            ],
            'campaigns' => Campaign::withCount(['assignments' => function($query) {
                $query->where('assignments.status', 'actif');
            }])->get(),
            'recentAlerts' => ActivityLog::latest()->take(10)->get()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD SUPERVISEUR
    |--------------------------------------------------------------------------
    */

    public function superviseur()
    {
        $manager = auth()->user()->employee;
        
        return Inertia::render('Dashboard/Superviseur', [
            'stats' => [
                'myAgents' => Assignment::where('manager_id', $manager?->id)->where('status', 'actif')->count(),
                'validatedTimesheets' => Timesheet::where('validated_by', $manager?->id)->count(),
                'pendingMyValidation' => Timesheet::where('status', 'soumis')
                    ->whereIn('employee_id', function($query) use ($manager) {
                        $query->select('employee_id')->from('assignments')->where('manager_id', $manager?->id);
                    })->count(),
            ],
            'myTeam' => Employee::whereIn('id', function($query) use ($manager) {
                $query->select('employee_id')->from('assignments')->where('manager_id', $manager?->id)->where('status', 'actif');
            })->with('position')->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD TÉLÉCONSEILLER
    |--------------------------------------------------------------------------
    */

    public function teleConseiller()
    {
        $employee = auth()->user()->employee;
        
        return Inertia::render('Dashboard/TeleConseiller', [
            'stats' => [
                'hoursWorkedMonth' => TimesheetEntry::whereHas('timesheet', function($q) use ($employee) {
                    $q->where('employee_id', $employee?->id);
                })->whereMonth('date', now()->month)->sum('total_hours'),
                'pendingValidation' => Timesheet::where('employee_id', $employee?->id)->where('status', 'soumis')->count(),
                'activeAssignment' => Assignment::where('employee_id', $employee?->id)->where('status', 'actif')->with('campaign')->first(),
            ],
            'recentTimesheets' => Timesheet::where('employee_id', $employee?->id)->latest()->take(5)->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STATISTIQUES GÉNÉRALES
    |--------------------------------------------------------------------------
    */

    public function generalStats()
    {
        return Inertia::render('Dashboard/Statistiques', [
            'summary' => [
                'total_worked' => (float) TimesheetEntry::sum('total_hours'),
                'total_planned' => (float) TimesheetEntry::sum('planned_hours'),
                'total_overtime' => (float) TimesheetEntry::sum('overtime_hours'),
                'avg_efficiency' => TimesheetEntry::where('planned_hours', '>', 0)
                    ->select(DB::raw('AVG(total_hours / planned_hours) * 100 as avg_eff'))
                    ->value('avg_eff') ?: 0
            ],

            'campaigns' => Campaign::withCount('assignments')
                ->get()
                ->map(function($campaign) {
                    $campaign->assignments_employee_timesheets_entries_sum_total_hours = TimesheetEntry::whereIn('timesheet_id', function($q) use ($campaign) {
                        $q->select('id')->from('timesheets')->whereIn('employee_id', function($q) use ($campaign) {
                            $q->select('employee_id')->from('assignments')->where('campaign_id', $campaign->id);
                        });
                    })->sum('total_hours');
                    return $campaign;
                }),

            'employees' => Employee::with('position')
                ->withSum('timesheetEntries', 'total_hours')
                ->withSum('timesheetEntries', 'overtime_hours')
                ->orderByDesc('timesheet_entries_sum_total_hours')
                ->take(15)
                ->get(),

            'dailyPerformance' => TimesheetEntry::select(
                'date',
                DB::raw('SUM(total_hours) as worked'),
                DB::raw('SUM(planned_hours) as planned')
            )
            ->where('date', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
        ]);
    }

    /**
     * Page des alertes et notifications pour l'admin
     */
    public function alerts()
    {
        // On récupère les 200 dernières alertes pour le filtrage automatique côté client (PrimeVue)
        return Inertia::render('Dashboard/Admin/Alerts', [
            'alerts' => ActivityLog::with('user')
                ->latest()
                ->take(200)
                ->get()
        ]);
    }

    /**
     * Rapport RH
     */
    public function hr()
    {
        return Inertia::render('Reports/Hr', [
            'stats' => [
                'total' => Employee::count(),
                'active' => Employee::where('status', 'actif')->count(),
                'inactive' => Employee::where('status', 'inactif')->count(),
            ],
            'positionDistribution' => DB::table('employees')
                ->join('positions', 'employees.position_id', '=', 'positions.id')
                ->select('positions.name', DB::raw('count(*) as count'))
                ->groupBy('positions.name')
                ->get()
        ]);
    }

    /**
     * Rapport Campagnes
     */
    public function campaigns()
    {
        return Inertia::render('Reports/Campaigns', [
            'campaigns' => Campaign::withCount('assignments')->get(),
            'stats' => [
                'total' => Campaign::count(),
                'active' => Campaign::where('status', 'active')->count(),
                'closed' => Campaign::where('status', 'closed')->count(),
            ]
        ]);
    }

    /**
     * Rapport Affectations
     */
    public function assignments()
    {
        return Inertia::render('Reports/Assignments', [
            'assignments' => Assignment::with(['employee.user', 'campaign', 'position', 'manager'])->latest()->get()
        ]);
    }

    /**
     * Rapport Heures
     */
    public function timesheets()
    {
        return Inertia::render('Reports/Timesheets', [
            'stats' => [
                'totalHours' => (float) TimesheetEntry::sum('total_hours'),
                'totalPlanned' => (float) TimesheetEntry::sum('planned_hours'),
                'totalOvertime' => (float) TimesheetEntry::sum('overtime_hours'),
            ],
            'weeklyStats' => TimesheetEntry::select(
                DB::raw('WEEK(date) as week'),
                DB::raw('SUM(total_hours) as hours'),
                DB::raw('SUM(planned_hours) as planned')
            )
            ->groupBy('week')
            ->orderBy('week', 'desc')
            ->take(12)
            ->get()
        ]);
    }

    /**
     * Rapport Productivité
     */
    public function productivity()
    {
        $productivityStats = Employee::withSum('timesheetEntries', 'total_hours')
            ->withSum('timesheetEntries', 'planned_hours')
            ->get()
            ->map(function ($employee) {
                $worked = (float) $employee->timesheet_entries_sum_total_hours;
                $planned = (float) $employee->timesheet_entries_sum_planned_hours ?: 1;
                return [
                    'id' => $employee->id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'worked' => $worked,
                    'planned' => $planned,
                    'ratio' => round(($worked / $planned) * 100, 2)
                ];
            })
            ->sortByDesc('ratio')
            ->values();

        return Inertia::render('Reports/Productivity', [
            'productivityStats' => $productivityStats
        ]);
    }

}
