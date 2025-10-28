<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * UC-Q1: Service de gestion des heures calmes
 * 
 * Gère les préférences d'heures calmes des utilisateurs
 * pour éviter l'envoi de notifications pendant ces périodes
 */
class QuietHoursService
{
    /**
     * UC-Q1: Vérifier si nous sommes dans les heures calmes pour un utilisateur
     */
    public function isQuietTime(User $user): bool
    {
        // Vérifier si les heures calmes sont activées
        if (!$user->quiet_hours_enabled) {
            Log::debug('Quiet hours disabled for user', ['user_id' => $user->id]);
            return false;
        }

        // Récupérer les préférences de l'utilisateur
        $quietStart = $user->quiet_hours_start ?? '22:00';
        $quietEnd = $user->quiet_hours_end ?? '07:00';
        
        if (!$quietStart || !$quietEnd) {
            Log::debug('Quiet hours not configured for user', ['user_id' => $user->id]);
            return false;
        }

        $timezone = $user->timezone ?? 'UTC';
        $now = Carbon::now($timezone);
        $currentTime = $now->format('H:i');
        
        $isQuiet = $this->isTimeInRange($currentTime, $quietStart, $quietEnd);
        
        Log::debug('Quiet hours check', [
            'user_id' => $user->id,
            'current_time' => $currentTime,
            'quiet_start' => $quietStart,
            'quiet_end' => $quietEnd,
            'timezone' => $timezone,
            'is_quiet' => $isQuiet
        ]);
        
        return $isQuiet;
    }

    /**
     * UC-Q2: Mettre à jour les préférences d'heures calmes d'un utilisateur
     */
    public function updateQuietHours(
        User $user, 
        ?string $startTime = null, 
        ?string $endTime = null, 
        ?bool $enabled = null,
        ?string $timezone = null
    ): bool {
        try {
            $updates = [];
            
            if ($startTime !== null) {
                if (!$this->isValidTime($startTime)) {
                    throw new \InvalidArgumentException("Invalid start time format: {$startTime}");
                }
                $updates['quiet_hours_start'] = $startTime;
            }
            
            if ($endTime !== null) {
                if (!$this->isValidTime($endTime)) {
                    throw new \InvalidArgumentException("Invalid end time format: {$endTime}");
                }
                $updates['quiet_hours_end'] = $endTime;
            }
            
            if ($enabled !== null) {
                $updates['quiet_hours_enabled'] = $enabled;
            }
            
            if ($timezone !== null) {
                if (!$this->isValidTimezone($timezone)) {
                    throw new \InvalidArgumentException("Invalid timezone: {$timezone}");
                }
                $updates['timezone'] = $timezone;
            }
            
            if (empty($updates)) {
                return true; // Rien à mettre à jour
            }
            
            $user->update($updates);
            
            Log::info('Quiet hours updated', [
                'user_id' => $user->id,
                'updates' => $updates
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to update quiet hours', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * UC-Q3: Obtenir les préférences d'heures calmes d'un utilisateur
     */
    public function getQuietHoursSettings(User $user): array
    {
        return [
            'enabled' => $user->quiet_hours_enabled ?? true,
            'start_time' => $user->quiet_hours_start ?? '22:00',
            'end_time' => $user->quiet_hours_end ?? '07:00',
            'timezone' => $user->timezone ?? 'UTC',
            'is_currently_quiet' => $this->isQuietTime($user)
        ];
    }

    /**
     * UC-Q4: Calculer le prochain moment où les notifications seront autorisées
     */
    public function getNextAllowedTime(User $user): ?Carbon
    {
        if (!$this->isQuietTime($user)) {
            return null; // Pas en période de silence, notifications autorisées maintenant
        }

        $timezone = $user->timezone ?? 'UTC';
        $now = Carbon::now($timezone);
        $quietEnd = $user->quiet_hours_end ?? '07:00';
        
        // Créer un Carbon pour l'heure de fin des heures calmes
        $endTime = Carbon::createFromFormat('H:i', $quietEnd, $timezone);
        
        // Si l'heure de fin est déjà passée aujourd'hui, c'est pour demain
        if ($endTime->lessThan($now)) {
            $endTime->addDay();
        }
        
        return $endTime;
    }

    /**
     * Vérifier si une heure est dans une plage donnée
     */
    private function isTimeInRange(string $currentTime, string $startTime, string $endTime): bool
    {
        // Si les heures calmes traversent minuit (ex: 22:00 - 07:00)
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        } else {
            // Heures calmes dans la même journée (ex: 13:00 - 14:00)
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }
    }

    /**
     * Valider le format d'une heure (HH:MM)
     */
    private function isValidTime(string $time): bool
    {
        if (empty($time)) {
            return false;
        }
        
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    /**
     * Valider le format du fuseau horaire
     */
    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list());
    }

    /**
     * Obtenir la liste des fuseaux horaires les plus courants
     */
    public function getCommonTimezones(): array
    {
        $timezones = [
            'Europe/Paris' => 'Paris (UTC+1/+2)',
            'Europe/London' => 'Londres (UTC+0/+1)',
            'Europe/Berlin' => 'Berlin (UTC+1/+2)',
            'Europe/Madrid' => 'Madrid (UTC+1/+2)',
            'Europe/Rome' => 'Rome (UTC+1/+2)',
            'Europe/Brussels' => 'Bruxelles (UTC+1/+2)',
            'Europe/Amsterdam' => 'Amsterdam (UTC+1/+2)',
            'Europe/Zurich' => 'Zurich (UTC+1/+2)',
            'America/New_York' => 'New York (UTC-5/-4)',
            'America/Los_Angeles' => 'Los Angeles (UTC-8/-7)',
            'America/Chicago' => 'Chicago (UTC-6/-5)',
            'America/Toronto' => 'Toronto (UTC-5/-4)',
            'America/Montreal' => 'Montréal (UTC-5/-4)',
            'Asia/Tokyo' => 'Tokyo (UTC+9)',
            'Asia/Shanghai' => 'Shanghai (UTC+8)',
            'Asia/Dubai' => 'Dubaï (UTC+4)',
            'Australia/Sydney' => 'Sydney (UTC+10/+11)',
            'Africa/Casablanca' => 'Casablanca (UTC+0/+1)',
            'Africa/Tunis' => 'Tunis (UTC+1)',
            'Africa/Algiers' => 'Alger (UTC+1)',
            'UTC' => 'UTC (Temps universel)',
        ];

        // Convertir en format attendu par le frontend
        return array_map(function ($label, $value) {
            return [
                'value' => $value,
                'label' => $label
            ];
        }, $timezones, array_keys($timezones));
    }
}