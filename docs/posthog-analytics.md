# AlertContacts PostHog Analytics

## Conclusion

PostHog doit etre lu comme un outil de decision produit, pas comme un compteur de visites. Le dashboard principal a pour but de repondre a quatre questions:

- Est-ce que les nouveaux utilisateurs terminent l'onboarding?
- Est-ce qu'ils atteignent le premier moment de valeur: au moins un contact accepte?
- Est-ce qu'ils creent des zones, signalements ou itineraires utiles?
- Est-ce que les utilisateurs premium se distinguent clairement des gratuits?

## Dashboard

Dashboard PostHog:

https://us.posthog.com/project/591000/dashboard/2060063

Les premiers insights crees avant l'instrumentation backend couvrent l'onboarding mobile:

- `Activation - Funnel onboarding complet`
- `Activation - Volumes events onboarding`
- `Activation - Personas selectionnees`
- `Tracking - Qualite events et identite`

Tant que le volume est faible, il faut lire les chiffres comme une validation technique du tracking, pas comme une verite business.

## Evenements Mobiles

Evenements deja vus dans PostHog:

- `onboarding_started`
- `onboarding_persona_selected`
- `onboarding_slides_completed`
- `auth_started`
- `login_success`
- `invitation_screen_viewed`
- `onboarding_invitation_skipped`
- `onboarding_completed`
- `app_shell_reached`
- `$screen`

Le tracking `$screen` est ajoute dans Flutter via le router. Il permet de savoir quelles sections sont vraiment vues sans publier une nouvelle version pour chaque nouveau funnel PostHog.

## Evenements Backend

Ces evenements sont envoyes par Laravel apres deploiement backend, sans mise a jour App Store:

- `backend_login_success`
- `backend_person_properties_updated`
- `contact_invited`
- `contact_invitation_accepted`
- `aha_1_contact_accepted`
- `zone_created`
- `community_alert_created`
- `community_alert_confirmed`
- `route_previewed`
- `route_started`
- `route_avoidance_requested`
- `route_avoidance_partial`
- `subscription_trial_started`
- `subscription_purchased`
- `subscription_renewed`
- `subscription_cancelled`
- `subscription_expired`

Les proprietes envoyees sont volontairement bucketisees. Exemple: `contacts_count_bucket`, `safe_zones_count_bucket`, `radius_bucket`, `distance_bucket`. Cela evite d'envoyer des donnees trop fines ou sensibles.

## Backfill

Pour estimer ce qui sera envoye:

```bash
php artisan analytics:posthog-backfill --dry-run --days=180
```

Pour envoyer l'historique en production:

```bash
php artisan analytics:posthog-backfill --days=180 --force
```

La commande utilise des UUID deterministes par source, id et evenement. Si elle est relancee, PostHog peut dedoublonner les evenements historiques qui ont le meme UUID.

## Lecture

Ordre de lecture recommande:

1. Verifier le funnel onboarding: le trou principal dit ou l'utilisateur bloque.
2. Verifier `aha_1_contact_accepted`: c'est le meilleur signal d'activation actuel.
3. Comparer les cohortes `subscription_tier`: gratuit vs premium.
4. Lire les usages reels: zones, alertes, itineraires.
5. Regarder la qualite tracking: personnes identifiees, events anonymes, doublons.

Un bon signal est une action qui prouve une valeur utilisateur. Exemple: `app_shell_reached` est moins fort que `contact_invitation_accepted`; `contact_invitation_accepted` est moins fort qu'un utilisateur qui cree une zone ou utilise un itineraire.

## Contraintes

Le backend respecte `analytics_consent === false`: aucun event PostHog n'est envoye pour ces utilisateurs.

Le `distinct_id` Laravel utilise `firebase_uid` quand il existe. C'est important pour relier les events mobiles Flutter et les events backend Laravel au meme utilisateur PostHog.

