<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationUpdatedEmail extends ReservationEmailMessage
{
    public function Subject()
    {
        // Was 'ReservationUpdatedAdminSubjectWithResource' — the *admin* subject line — even
        // though this is the owner-facing copy (ReservationUpdatedEmail, not
        // ReservationUpdatedEmailAdmin). Fixed to use the matching non-admin key.
        return $this->Translate('ReservationUpdatedSubjectWithResource', [$this->primaryResource->GetName()]);
    }

    protected function PopulateTemplate()
    {
        parent::PopulateTemplate();
        $this->Set('Status', 'updated');

        $changes = [];
        if ($this->reservationSeries instanceof ExistingReservationSeries) {
            foreach ($this->reservationSeries->GetChangeSummary() as $change) {
                $changes[] = $this->FormatChange($change);
            }
        }
        $this->Set('Changes', $changes);
    }

    /**
     * Turns one ExistingReservationSeries::GetChangeSummary() entry into display-ready
     * before/after strings — Duration needs date formatting (in the recipient's timezone,
     * same as StartDate/EndDate elsewhere in this email), the others are already plain text.
     *
     * Labels are hardcoded Chinese here rather than routed through Translate() — same reasoning
     * as the rest of this email body (see lang/en_us/ReservationCreated.tpl and
     * COASIA-CHANGES.md §2): guest recipients have no account language preference and always
     * fall back to the system default, so anything that must reliably read in Chinese for every
     * recipient is written directly rather than depending on a language setting.
     *
     * @param array $change
     * @return array ['label' => string, 'before' => string, 'after' => string]
     */
    private function FormatChange($change)
    {
        $labels = [
            'Title' => '會議名稱',
            'Description' => '備註',
            'Resource' => '會議室／資源',
            'Duration' => '日期時間',
        ];
        $label = $labels[$change['field']] ?? $change['field'];

        if ($change['field'] === 'Duration') {
            /** @var DateRange $before */
            /** @var DateRange $after */
            $before = $change['before'];
            $after = $change['after'];
            // Same date format the rest of this email uses (formatdate ... key=reservation_email
            // in the .tpl) so an updated-time row reads consistently with StartDate/EndDate above it.
            $dateFormat = Resources::GetInstance()->GetDateFormat('reservation_email');
            $format = function (DateRange $range) use ($dateFormat) {
                return $range->GetBegin()->ToTimezone($this->timezone)->Format($dateFormat) . ' - ' .
                    $range->GetEnd()->ToTimezone($this->timezone)->Format($dateFormat);
            };
            return ['label' => $label, 'before' => $format($before), 'after' => $format($after)];
        }

        return [
            'label' => $label,
            'before' => (string)($change['before'] ?? ''),
            'after' => (string)($change['after'] ?? ''),
        ];
    }

    protected function GetTemplateName()
    {
        return 'ReservationCreated.tpl';
    }

    protected function IsRecipientOwner(): bool
    {
        return true;
    }
}
