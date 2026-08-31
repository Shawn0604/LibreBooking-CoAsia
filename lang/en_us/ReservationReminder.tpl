{* Used by ReservationReminderEmail — the "會議開始前提醒" e-mail. All content lives in the
   shared partial; $Status = 'reminder' (set by ReservationReminderEmail::PopulateTemplate())
   drives the reminder variant. Same one-template-many-statuses approach as
   lang/en_us/ReservationCreated.tpl. *}
{include file='Email/reservation-notification-body.tpl'}
