<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Constants\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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

    public const BULK_UPLOAD_SUPERADMIN_EMAIL = 'avirup.maitra@bridgeroof.co.in';

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN || $this->isBulkUploadSuperAdmin();
    }

    public function isBulkUploadSuperAdmin(): bool
    {
        return strcasecmp($this->email, self::BULK_UPLOAD_SUPERADMIN_EMAIL) === 0;
    }

    public function isUploader(): bool
    {
        return $this->role === Role::UPLOADER;
    }

    public function isViewer(): bool
    {
        return $this->role === Role::VIEWER;
    }
}
