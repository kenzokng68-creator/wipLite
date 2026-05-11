<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Campaign;
use App\Models\User;

class AssignmentHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'employee_id',
        'old_manager_id',
        'new_manager_id',
        'old_campaign_id',
        'new_campaign_id',
        'action_type',
        'changed_by',
        'reason'
    ];

    /**
     * L'affectation liée à cette action
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Employé concerné
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Ancien manager
     */
    public function oldManager()
    {
        return $this->belongsTo(Employee::class, 'old_manager_id');
    }

    /**
     * Nouveau manager
     */
    public function newManager()
    {
        return $this->belongsTo(Employee::class, 'new_manager_id');
    }

    /**
     * Ancienne campagne
     */
    public function oldCampaign()
    {
        return $this->belongsTo(Campaign::class, 'old_campaign_id');
    }

    /**
     * Nouvelle campagne
     */
    public function newCampaign()
    {
        return $this->belongsTo(Campaign::class, 'new_campaign_id');
    }

    /**
     * Utilisateur qui a fait l'action
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}