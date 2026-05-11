<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimesheetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'timesheet_id',
        'date',
        'check_in',
        'check_out',
        'break_duration',
        'total_hours',
        'planned_hours',
        'overtime_hours',
        'absence_type',
        'comment'
    ];

    protected $casts = [
        'date' => 'date',
        'total_hours' => 'decimal:2',
        'planned_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
    public function calculateHours()
    {
        if ($this->check_in && $this->check_out) {
            $start = Carbon::parse($this->check_in);
            $end = Carbon::parse($this->check_out);

            $diff = $start->diffInMinutes($end) / 60;
            $this->total_hours = $diff - ($this->break_duration / 60);

            $this->overtime_hours = max(0, $this->total_hours - $this->planned_hours);
        }
    }

    /**
     * Récupère dynamiquement les heures prévues si elles ne sont pas encore fixées.
     */
    public function getPlannedHoursAttribute($value)
    {
        if ($value > 0) return $value;

        // Si la valeur en BDD est 0 ou nulle, on cherche dans le planning
        $date = Carbon::parse($this->date);
        $dayName = strtolower($date->format('l'));
        $columnName = $dayName . '_hours';

        $assignment = PlanningAssignment::where('employee_id', $this->timesheet->employee_id)
            ->where('start_date', '<=', $this->date)
            ->where(function ($q) {
                $q->where('end_date', '>=', $this->date)
                    ->orWhereNull('end_date');
            })
            ->where('status', 'validé')
            ->first();

        return $assignment ? $assignment->planningModel->$columnName : 0;
    }

    public function isAbsent()
    {
        return !is_null($this->absence_type);
    }
    public function logs()
{
    return $this->morphMany(ActivityLog::class, 'model');
}
}
