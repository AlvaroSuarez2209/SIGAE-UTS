<?php

namespace App\Models;

use App\Enums\RoleName;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'document_number',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(Leadership::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /**
     * Whether this user currently leads the scope (program unit + activity)
     * that the given teacher assignment belongs to — i.e. the "ámbito" a
     * líder is authorized to see, per a leadership with no end date or one
     * that covers today, either for the whole program unit or narrowed to
     * this specific activity.
     */
    public function canLeadAssignment(TeacherAssignment $assignment): bool
    {
        return $this->leaderships()
            ->where('academic_period_id', $assignment->academic_period_id)
            ->where('program_unit_id', $assignment->program_unit_id)
            ->where(fn ($query) => $query
                ->whereNull('activity_id')
                ->orWhere('activity_id', $assignment->activity_id)
            )
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->exists();
    }

    public function hasRole(RoleName|string $role): bool
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return $this->roles->contains('name', $name);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
