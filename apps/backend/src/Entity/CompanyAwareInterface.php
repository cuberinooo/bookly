<?php

namespace App\Entity;

interface CompanyAwareInterface
{
    public function getCompany(): ?Company;
    public function setCompany(?Company $company): static;
}
