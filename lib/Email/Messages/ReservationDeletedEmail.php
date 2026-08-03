<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationDeletedEmail extends ReservationEmailMessage
{
    /**
     * @return string
     */
    public function Subject()
    {
        return $this->Translate('ReservationDeletedSubjectWithResource', [$this->primaryResource->GetName()]);
    }

    public function PopulateTemplate()
    {
        parent::PopulateTemplate();
        if (method_exists($this->reservationSeries, 'GetDeleteReason')) {
            $this->Set('DeleteReason', $this->reservationSeries->GetDeleteReason());
        }
        $this->Set('Deleted', true);
        $this->Set('Status', 'cancelled');
    }

    protected function GetIcsMethod()
    {
        return 'CANCEL';
    }

    protected function GetTemplateName()
    {
        return 'ReservationDeleted.tpl';
    }

    // Safe on THIS class specifically (To() is never overridden here, so it always sends to
    // the owner) -- but GuestDeletedEmail and InviteeRemovedEmail both extend this class and
    // DO override To() to a non-owner address, so each of those re-overrides this back to
    // false explicitly rather than relying on "they just don't call it".
    protected function IsRecipientOwner(): bool
    {
        return true;
    }
}
