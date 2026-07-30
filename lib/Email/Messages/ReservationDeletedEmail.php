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
}
