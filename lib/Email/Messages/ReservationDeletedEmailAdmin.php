<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationCreatedEmailAdmin.php');

class ReservationDeletedEmailAdmin extends ReservationCreatedEmailAdmin
{
    public function Subject()
    {
        return $this->Translate('ReservationDeleteAdminSubjectWithResource', [$this->resource->GetName()]);
    }

    public function PopulateTemplate()
    {
        if (method_exists($this->reservationSeries, 'GetDeleteReason')) {
            $this->Set('DeleteReason', $this->reservationSeries->GetDeleteReason());
        }
        parent::PopulateTemplate();
        // Renders through the same shared partial as the owner-facing cancellation email (see
        // GetTemplateName() below) — needs $Status set for the same reason. This class extends
        // ReservationCreatedEmailAdmin (-> EmailMessage), a separate hierarchy from
        // ReservationEmailMessage, so it has no ICS attachment / GetIcsMethod() to worry about —
        // admin copies don't carry one today.
        $this->Set('Deleted', true);
        $this->Set('Status', 'cancelled');
    }

    protected function GetTemplateName()
    {
        return 'ReservationDeleted.tpl';
    }
}
