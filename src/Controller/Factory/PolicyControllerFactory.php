<?php
namespace MKDF\Policies\Controller\Factory;

use MKDF\Policies\Controller\PolicyController;
use MKDF\Core\Repository\MKDFCoreRepositoryInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Zend\Session\SessionManager;

class PolicyControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $core_repository = $container->get(MKDFCoreRepositoryInterface::class);
        $sessionManager = $container->get(SessionManager::class);
        return new PolicyController($core_repository, $config);
    }

}