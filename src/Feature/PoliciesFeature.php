<?php

namespace MKDF\Policies\Feature;

use MKDF\Core\Service\AccountFeatureInterface;

class PoliciesFeature implements AccountFeatureInterface
{
    private $active = false;

    public function getController() {
        return \MKDF\Policies\Controller\PolicyController::class;
    }
    public function getViewAction(){
        return 'index';
    }
    public function getEditAction(){
        return 'index';
    }
    public function getViewHref(){
        return '/my-account/key';
    }
    public function getEditHref(){
        return '/my-account/key';
    }
    public function hasFeature(){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return '<i class="fas fa-key"></i> My keys';
    }
    public function isActive(){
        return $this->active;
    }
    public function setActive($bool){
        $this->active = !!$bool;
    }

}