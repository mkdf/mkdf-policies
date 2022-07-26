<?php
namespace MKDF\Policies\Controller;

use MKDF\Core\Repository\MKDFCoreRepositoryInterface;
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\Keys\Form\KeyForm;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator\Paginator;
use Zend\Paginator\Adapter;
use Zend\View\Model\ViewModel;
use Zend\Session\SessionManager;
use Zend\Session\Container;

class PolicyController extends AbstractActionController
{
    private $_config;
    private $_repository;
    private $_stream_repository;

    public function __construct(MKDFKeysRepositoryInterface $repository, MKDFCoreRepositoryInterface $core_repository, MKDFStreamRepositoryInterface $stream_repository, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_core_repository = $core_repository;
        $this->_stream_repository = $stream_repository;
    }
    
    public function indexAction() {
    }
    
    public function detailsAction() {
    }

}
