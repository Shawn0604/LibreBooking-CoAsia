{* Used by ReservationDeletedEmail (cancelled) — see ReservationDeletedEmail::GetTemplateName()
   in lib/Email/Messages/. All visual/content logic lives in the shared partial; $Status='cancelled'
   (set by ReservationDeletedEmail::PopulateTemplate()) drives the cancelled-specific rendering
   (no join-meeting button, cancellation reason shown instead of the usual body). *}
{include file='Email/reservation-notification-body.tpl'}
