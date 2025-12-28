<?php

namespace App\Controller\Admin;

use App\Entity\Parti;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class PartiCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Parti::class;
    }
}
