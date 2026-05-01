<?php

namespace App\Doctrine;

use App\Entity\CompanyAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class CompanyFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Check if the entity implements CompanyAwareInterface
        if (!$targetEntity->reflClass->implementsInterface(CompanyAwareInterface::class)) {
            return "";
        }

        try {
            $companyId = $this->getParameter('company_id');
            if (!$companyId) {
                return "";
            }
        } catch (\InvalidArgumentException $e) {
            return "";
        }

        return $targetTableAlias . '.company_id = ' . $companyId;
    }
}
