<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

// TODO: Need a way to unit test this
class ReservationCreatedEmail extends ReservationEmailMessage
{
    public function Subject()
    {
        if ($this->reservationSeries->RequiresApproval()) {
            return $this->Translate('ReservationPendingApprovalSubjectWithResource', [$this->primaryResource->GetName()]);
        }
        return $this->Translate('ReservationCreatedSubjectWithResource', [$this->primaryResource->GetName()]);
    }

    protected function PopulateTemplate()
    {
        parent::PopulateTemplate();
        $this->Set('Status', $this->reservationSeries->RequiresApproval() ? 'pending' : 'created');
    }

    protected function GetTemplateName()
    {
        return 'ReservationCreated.tpl';
    }
}
