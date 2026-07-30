{* Used by ReservationCreatedEmail (created/pending), ReservationUpdatedEmail (updated), and
   ReservationApprovedEmail (approved) — see each class's GetTemplateName() in
   lib/Email/Messages/. All visual/content logic lives in the shared partial; $Status (set by
   each class's PopulateTemplate()) drives which variant renders. *}
{include file='Email/reservation-notification-body.tpl'}
