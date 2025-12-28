<?php

namespace App\Controller\Admin;

use App\Entity\Election;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ElectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Election::class;
    }
}
