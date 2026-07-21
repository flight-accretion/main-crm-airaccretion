<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadPassenger extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'voucher_id',
        'lead_id',
        'registration_token',
        'registration_slug',
        'token_expires_at',
        'name',
        'age',
        'contact_number',
        'traveller_type',
        'weight',
        'front_document',
        'back_document',
        'is_handler',
        'is_additional_person'
    ];

    protected $casts = [
        'is_handler' => 'boolean',
        'is_additional_person' => 'boolean',
        'token_expires_at' => 'datetime'
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function generateRegistrationToken()
    {
        $this->registration_token = \Illuminate\Support\Str::random(64);
        $this->token_expires_at = now()->addDays(30); // Token expires in 30 days
        $this->save();
        return $this->registration_token;
    }

    /**
     * Generate a short slug (human-friendly) for sharing short URLs.
     */
    public function generateRegistrationSlug()
    {
        // 8 character base62 slug
        do {
            $slug = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 8);
        } while (self::where('registration_slug', $slug)->exists());

        $this->registration_slug = $slug;
        $this->save();
        return $this->registration_slug;
    }

    /**
     * Return a short registration link using the slug if present.
     */
    public function getShortRegistrationLink()
    {
        if (empty($this->registration_slug)) {
            return null;
        }
        return route('lead.register.short', ['slug' => $this->registration_slug]);
    }

    public function getRegistrationLink()
    {
        if (empty($this->registration_token)) {
            return null;
        }
        return route('lead.register.form', ['lead' => $this->lead_id, 'token' => $this->registration_token]);
    }

    public function isTokenValid()
    {
        return !empty($this->registration_token) && 
               !empty($this->token_expires_at) && 
               $this->token_expires_at->isFuture();
    }
}