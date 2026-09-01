<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/ReminderRepository.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationReminder.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/Reminder/ReservationReminderSender.php');

/**
 * Job glue for Jobs/sendreminders.php: find the reminders that come due at this minute and
 * hand each one (once) to {@see ReservationReminderSender}, which owns the "who gets it, over
 * which channel" policy.
 *
 * GetReminderNotices() returns one row per reservation_user, so a reservation with N
 * attendees yields N identical due rows — they are collapsed here by reference number so the
 * fan-out runs exactly once per reservation.
 */
class ReservationReminderDispatcher
{
    private IReminderRepository $reminderRepository;
    private IReservationRepository $reservationRepository;
    private ReservationReminderSender $sender;

    public function __construct(
        IReminderRepository $reminderRepository,
        IReservationRepository $reservationRepository,
        ReservationReminderSender $sender
    ) {
        $this->reminderRepository = $reminderRepository;
        $this->reservationRepository = $reservationRepository;
        $this->sender = $sender;
    }

    public function DispatchDue(Date $now): void
    {
        $this->DispatchType($now, ReservationReminderType::Start);
        $this->DispatchType($now, ReservationReminderType::End);
    }

    private function DispatchType(Date $now, $reminderType): void
    {
        $notices = $this->reminderRepository->GetReminderNotices($now, $reminderType);
        Log::Debug(
            'sendreminders: %s due %s-reminder rows',
            count($notices),
            $reminderType === ReservationReminderType::End ? 'end' : 'start'
        );

        $handled = [];
        foreach ($notices as $notice) {
            $referenceNumber = $notice->ReferenceNumber();
            if (isset($handled[$referenceNumber])) {
                continue;
            }
            $handled[$referenceNumber] = true;

            try {
                $series = $this->reservationRepository->LoadByReferenceNumber($referenceNumber);
                if ($series === null) {
                    Log::Debug('sendreminders: reference %s no longer loads, skipping', $referenceNumber);
                    continue;
                }
                $this->sender->Send($series, $reminderType, (int)$notice->ReminderMinutes());
            } catch (Throwable $ex) {
                // Throwable, not Exception: a TypeError building one reminder's e-mail must not
                // kill every other due reminder this minute (there is no catch-up run).
                Log::Error('sendreminders: failed dispatching reference %s: %s', $referenceNumber, $ex);
            }
        }
    }
}
