<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['employee_id', 'campaign_id', 'manager_id', 
        'position_id', 'status', 'start_date', 'end_date'])]
class Assignment extends Model
{
    use HasFactory;
        // Relation : L'employé qui occupe ce poste
    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    // Relation : La campagne concernée
    public function campaign() {
        return $this->belongsTo(Campaign::class);
    }

    // Relation : Le manager direct
    public function manager() {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function position() {
        return $this->belongsTo(Position::class);
    }

}
