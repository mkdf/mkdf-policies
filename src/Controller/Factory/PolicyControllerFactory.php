<?php
namespace MKDF\Policies\Controller\Factory;

use MKDF\Policies\Repository\PoliciesRepositoryInterface;
use MKDF\Policies\Repository\PoliciesRepository;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManagerInterface;
use MKDF\Stream\Repository\Factory\MKDFStreamRepositoryFactory;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use MKDF\Policies\Controller\PolicyController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class PolicyControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $viewRenderer = $container->get('ViewRenderer');
        $dataset_repository = $container->get(MKDFDatasetRepositoryInterface::class);
        $repository = $container->get(MKDFStreamRepositoryInterface::class);
        $policyRepository = $container->get(PoliciesRepositoryInterface::class);
        $permissionManager = $container->get(DatasetPermissionManagerInterface::class);
        return new PolicyController($policyRepository, $repository, $dataset_repository, $permissionManager, $config, $viewRenderer);
    }
}