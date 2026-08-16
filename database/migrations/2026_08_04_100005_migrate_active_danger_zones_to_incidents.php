<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Migration de données — alertes communautaires V4.0 → incidents V4.1
 *
 * En V4.0, les alertes communautaires étaient écrites dans la table legacy
 * `danger_zones` (AlertController@store). On reprend ici les alertes encore
 * vivantes pour ne pas vider la carte le jour du déploiement.
 *
 * `danger_zones` n'est ni supprimée ni modifiée : le flux « zones de danger
 * persistantes » (DangerZonesController, GeoprocessingService, Filament)
 * continue de tourner dessus.
 *
 * Géométrie : polygone serré (§4.6 cas 2). Aucune trace GPS n'a été conservée
 * en V4.0, un corridor est donc impossible à reconstituer rétroactivement.
 */
return new class extends Migration
{
    /**
     * 21 valeurs `danger_type` legacy (EN + FR mélangés) → 10 types V4.1 §4.9.
     */
    private const TYPE_MAP = [
        // V4 (anglais)
        'accident'                => 'accident',
        'suspect'                 => 'suspect',
        'fire'                    => 'fire',
        'aggression'              => 'aggression',
        'suspicious_package'      => 'suspicious_package',
        'other'                   => 'other',
        // Legacy (français)
        'agression'               => 'aggression',
        'vol'                     => 'aggression',
        'braquage'                => 'aggression',
        'harcelement'             => 'aggression',
        'zone_non_eclairee'       => 'other',
        'zone_marecageuse'        => 'other',
        'accident_frequent'       => 'accident',
        'deal_drogue'             => 'other',
        'vandalisme'              => 'other',
        'zone_deserte'            => 'other',
        'construction_dangereuse' => 'roadworks',
        'animaux_errants'         => 'other',
        'manifestation'           => 'protest',
        'inondation'              => 'flood',
        'autre'                   => 'other',
    ];

    public function up(): void
    {
        $types = config('incidents.types', []);
        $vertices = config('incidents.geometry.polygon_vertices', 12);
        $now = Carbon::now();

        DB::table('danger_zones')
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(200, function ($zones) use ($types, $vertices, $now) {
                foreach ($zones as $zone) {
                    $type = self::TYPE_MAP[$zone->danger_type] ?? 'other';
                    $config = $types[$type] ?? $types['other'] ?? [];

                    $ttlMinutes = $config['ttl_minutes'] ?? 60;
                    $lastReport = Carbon::parse($zone->last_report_at ?? $zone->created_at ?? $now);

                    // On ne reprend que les alertes encore vivantes selon le TTL V4.1
                    if ($lastReport->copy()->addMinutes($ttlMinutes)->isPast()) {
                        continue;
                    }

                    $lat = (float) $zone->center_lat;
                    $lng = (float) $zone->center_lng;
                    $radius = (int) ($config['polygon_fallback_radius_m'] ?? 80);
                    $polygon = $this->circleToPolygon($lat, $lng, $radius, $vertices);

                    $lats = array_column($polygon, 0);
                    $lngs = array_column($polygon, 1);

                    $severity = in_array($zone->severity, ['low', 'medium', 'high'], true)
                        ? $zone->severity
                        : ($config['severity_default'] ?? 'medium');

                    $reportCount = max(1, (int) $zone->confirmations);

                    $incidentId = DB::table('incidents')->insertGetId([
                        'type'             => $type,
                        'severity'         => $severity,
                        'geometry_type'    => 'polygon',
                        'geometry'         => json_encode($polygon),
                        'danger_buffer_m'  => $config['danger_buffer_m'] ?? 20,
                        'notify_radius_m'  => $config['notify_radius_m'] ?? 500,
                        'display_radius_m' => $config['display_radius_m'] ?? 200,
                        'centroid_lat'     => $lat,
                        'centroid_lng'     => $lng,
                        'bbox_north'       => max($lats),
                        'bbox_south'       => min($lats),
                        'bbox_east'        => max($lngs),
                        'bbox_west'        => min($lngs),
                        'report_count'     => $reportCount,
                        'confirm_count'    => (int) $zone->confirmations,
                        'clear_count'      => 0,
                        'confidence_score' => min(1, 0.25 * $reportCount),
                        // Reprise conservatrice : la géométrie est un repli imprécis,
                        // on laisse RouteAvoidancePolicy réévaluer au prochain signalement.
                        'affects_routing'  => false,
                        'status'           => 'active',
                        'expires_at'       => $lastReport->copy()->addMinutes($ttlMinutes),
                        'created_at'       => $zone->created_at ?? $now,
                        'updated_at'       => $now,
                    ]);

                    // Signalement d'origine — sans auteur si l'alerte était anonyme
                    if ($zone->reported_by !== null) {
                        DB::table('alert_reports')->insert([
                            'incident_id'    => $incidentId,
                            'user_id'        => $zone->reported_by,
                            'type'           => $type,
                            'severity'       => $severity,
                            'lat'            => $lat,
                            'lng'            => $lng,
                            'gps_accuracy_m' => null,
                            'gps_trace'      => null,
                            'was_moving'     => false,
                            'comment'        => $zone->description,
                            'visibility'     => ($zone->visibility ?? 'public') === 'contacts_only' ? 'circle' : 'public',
                            'created_at'     => $zone->created_at ?? $now,
                            'updated_at'     => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Les incidents issus de la reprise n'ont pas de marqueur dédié.
        // Le rollback des tables elles-mêmes est assuré par les migrations
        // create_* qui précèdent ; rien à défaire ici.
    }

    /**
     * Cercle → polygone à N sommets (CDC V4.1 §17.A).
     * Dupliqué volontairement depuis App\Support\Geo : une migration ne doit
     * pas dépendre d'un service applicatif susceptible d'évoluer.
     */
    private function circleToPolygon(float $lat, float $lng, int $radiusM, int $n): array
    {
        $points = [];
        $latRad = deg2rad($lat);

        for ($k = 0; $k < $n; $k++) {
            $theta = 2 * M_PI * $k / $n;
            $points[] = [
                round($lat + ($radiusM / 111320) * cos($theta), 7),
                round($lng + ($radiusM / (111320 * max(cos($latRad), 0.000001))) * sin($theta), 7),
            ];
        }

        return $points;
    }
};
