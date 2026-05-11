<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    /**
     * @use HasFactory<\Database\Factories\ArticleFactory>
     */
    use HasFactory;
    protected $fillable = [
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'email',
        'address',
        'position_id',
        'salary_base',
        'status'
    ];

    // les modifications sur ces champs
    protected $casts = [
        'birth_date' => 'date',
        'salary_base' => 'decimal:2',
    ];

    /**
     * Boot the model.
     */
    

    /**
     * Génère un matricule unique (ex: EMP-2026-0001)
     */
   

    // Statuts disponibles
    const STATUS_ACTIF     = 'actif';
    const STATUS_INACTIF   = 'inactif';
    const STATUS_SUSPENDU  = 'suspendu';

    public static array $statuses = [
        self::STATUS_ACTIF,
        self::STATUS_INACTIF,
        self::STATUS_SUSPENDU,
    ];

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function timesheetEntries(): HasManyThrough
    {
        return $this->hasManyThrough(TimesheetEntry::class, Timesheet::class);
    }


    // -------------------------------------------------------
    // BOOT
    // -------------------------------------------------------
    protected static function boot(): void
    {
        parent::boot();
 
        // Génération automatique du matricule
        static::creating(function (Employee $employee) {
            if (empty($employee->matricule)) {
                $employee->matricule = self::generateMatricule();
            }
        });
 
        // Historisation automatique — uniquement position et statut
        static::updating(function (Employee $employee) {
            $dirty = $employee->getDirty();
 
            // On ne track que si position_id ou status a changé
            $hasPositionChange = array_key_exists('position_id', $dirty);
            $hasStatusChange   = array_key_exists('status', $dirty);
 
            if ($hasPositionChange || $hasStatusChange) {
                EmployeeHistory::create([
                    'employee_id'     => $employee->id,
                    'old_position_id' => $employee->getOriginal('position_id'),
                    'new_position_id' => $dirty['position_id'] ?? $employee->getOriginal('position_id'),
                    'old_status'      => $employee->getOriginal('status'),
                    'new_status'      => $dirty['status'] ?? $employee->getOriginal('status'),
                    'changed_by'      => auth()->id(),
                ]);
            }
        });
    }

    /**
     * Génère un matricule unique au format
     */
    public static function generateMatricule(): string
    {
        $prefix = 'EMP-' . now()->format('Ymd') . '-';
        $last   = self::where('matricule', 'like', $prefix . '%')
                      ->orderByDesc('matricule')
                      ->value('matricule');

        $next = $last
            ? (int) substr($last, -4) + 1
            : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------

    /**
     * Le poste actuel de l'employé
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * L'employé peut être lié à un compte utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Historique des modifications de l'employé
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class)->latest();
    }

    /**
     * Les affectations de l'employé aux campagnes
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }


    // -------------------------------------------------------
    // Filtres réutilisables
    // -------------------------------------------------------

    /** Filtrer par statut */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /** Seulement les employés actifs */
    public function scopeActif($query)
    {
        return $query->where('status', self::STATUS_ACTIF);
    }

    /** Filtrer par poste */
    public function scopeByPosition($query, int $positionId)
    {
        return $query->where('position_id', $positionId);
    }

    /** Recherche globale (nom, prénom, matricule, email) */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name',  'like', "%{$term}%")
              ->orWhere('matricule',  'like', "%{$term}%")
              ->orWhere('email',      'like', "%{$term}%");
        });
    }


    /** Nom complet de l'employé */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'model');
    }
}
