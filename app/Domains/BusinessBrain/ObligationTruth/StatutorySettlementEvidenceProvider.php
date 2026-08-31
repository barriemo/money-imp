<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

interface StatutorySettlementEvidenceProvider
{
    public function assess(): StatutorySettlementEvidence;
}
